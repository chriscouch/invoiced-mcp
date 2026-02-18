<?php

namespace App\Sending\Models;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Sending\Libs\ScheduledSendParameterValidator;
use App\Sending\ValueObjects\ScheduledSendParameters;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * Models a request to send out an invoice using some given
 * channel.
 *
 * @property int                $id
 * @property int|null           $invoice_id
 * @property Invoice|null       $invoice
 * @property int|null           $credit_note_id
 * @property CreditNote|null    $credit_note
 * @property int|null           $estimate_id
 * @property Estimate|null      $estimate
 * @property int                $channel
 * @property array|null         $parameters
 * @property string|null        $send_after
 * @property bool               $sent
 * @property bool               $canceled
 * @property bool               $skipped
 * @property bool               $failed
 * @property string|null        $failure_detail
 * @property string|null        $sent_at
 * @property string|null        $reference
 * @property int|null           $replacement_id
 * @property ScheduledSend|null $replacement
 */
class ScheduledSend extends MultitenantModel
{
    use AutoTimestamps;

    const EMAIL_CHANNEL = 0;
    const EMAIL_CHANNEL_STR = 'email';
    const SMS_CHANNEL = 1;
    const SMS_CHANNEL_STR = 'sms';
    const LETTER_CHANNEL = 2;
    const LETTER_CHANNEL_STR = 'letter';

    protected static function getProperties(): array
    {
        return [
            'invoice' => new Property(
                null: true,
                belongs_to: Invoice::class,
            ),
            'credit_note' => new Property(
                null: true,
                belongs_to: CreditNote::class,
            ),
            'estimate' => new Property(
                null: true,
                belongs_to: Estimate::class,
            ),
            'channel' => new Property(
                type: Type::INTEGER,
                validate: ['enum', 'choices' => [self::EMAIL_CHANNEL, self::SMS_CHANNEL, self::LETTER_CHANNEL]],
            ),
            'parameters' => new Property(
                type: Type::ARRAY,
                null: true,
            ),
            'send_after' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'sent' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'canceled' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'skipped' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'failed' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'failure_detail' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'ignore_failure' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'sent_at' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'reference' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'replacement' => new Property(
                null: true,
                in_array: false,
                belongs_to: ScheduledSend::class,
            ),
        ];
    }

    public function initialize(): void
    {
        parent::initialize();
        self::saving([self::class, 'validateDocument']);
        self::saving([self::class, 'validateParameters']);
    }

    /**
     * Validates the document associated w/ the ScheduledSend.
     *
     * @throws ListenerException
     */
    public static function validateDocument(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $documentProps = [
            'invoice_id',
            'credit_note_id',
            'estimate_id',
        ];

        // look for document
        $hasDocument = false;
        foreach ($documentProps as $prop) {
            if ($model->$prop) {
                if ($hasDocument) {
                    throw new ListenerException('Multiple documents is unsupported');
                }

                $hasDocument = true;
            }
        }

        if (!$hasDocument) {
            throw new ListenerException('Required document is missing');
        }

        // check for unsupported (document,channel) combination
        if (!$model->invoice_id && self::EMAIL_CHANNEL !== $model->channel) {
            $channel = $model->getChannel();
            $unsupportedType = $model->credit_note_id ? 'credit_note' : 'estimate';

            throw new ListenerException("The channel '$channel' does not support the document type: $unsupportedType");
        }
    }

    /**
     * @throws ListenerException
     */
    public static function validateParameters(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();

        if ($model->dirty('parameters') && is_array($model->parameters)) {
            try {
                ScheduledSendParameterValidator::validate($model->channel, $model->parameters);
            } catch (InvalidArgumentException $e) {
                throw new ListenerException($e->getMessage());
            }
        }
    }

    //
    // Getters
    //

    /**
     * Returns a Pulsar Query object configured to query for ScheduledSends
     * which are ready to be processed.
     */
    public static function getNotSentQuery(): Query
    {
        return ScheduledSend::query()
            ->where('sent', false)
            ->where('canceled', false)
            ->where('skipped', false)
            ->where('failed', false);
    }

    public function getChannel(): string
    {
        return match ($this->channel) {
            self::EMAIL_CHANNEL => self::EMAIL_CHANNEL_STR,
            self::SMS_CHANNEL => self::SMS_CHANNEL_STR,
            self::LETTER_CHANNEL => self::LETTER_CHANNEL_STR,
            default => '',
        };
    }

    public function getDocument(): ?ReceivableDocument
    {
        if ($invoice = $this->invoice) {
            return $invoice;
        }

        if ($creditNote = $this->credit_note) {
            return $creditNote;
        }

        if ($estimate = $this->estimate) {
            return $estimate;
        }

        return null;
    }

    public function getParameters(): ScheduledSendParameters
    {
        $to = $this->parameters['to'] ?? null;
        $cc = $this->parameters['cc'] ?? null;
        $bcc = $this->parameters['bcc'] ?? null;
        $subject = $this->parameters['subject'] ?? null;
        $message = $this->parameters['message'] ?? null;
        $role = $this->parameters['role'] ?? null;

        return new ScheduledSendParameters($to, $cc, $bcc, $subject, $message, $role);
    }

    /**
     * Whether the ScheduledSend has been attempted to be sent.
     */
    public function attempted(bool $includeCanceled = true): bool
    {
        if ($this->replacement) {
            return $this->replacement->attempted($includeCanceled);
        }

        return $this->sent || $this->failed || $this->skipped || ($includeCanceled && $this->canceled);
    }

    public function getSendAfter(): ?CarbonImmutable
    {
        if (!$this->send_after) {
            return null;
        }

        return new CarbonImmutable($this->send_after);
    }

    public function getSentAt(): ?CarbonImmutable
    {
        if (!$this->sent_at) {
            return null;
        }

        return new CarbonImmutable($this->sent_at);
    }

    public static function getChannelId(string $channel): int
    {
        return match ($channel) {
            self::EMAIL_CHANNEL_STR => self::EMAIL_CHANNEL,
            self::SMS_CHANNEL_STR => self::SMS_CHANNEL,
            self::LETTER_CHANNEL_STR => self::LETTER_CHANNEL,
            default => 0,
        };
    }

    public static function getChannelString(int $channel): string
    {
        return match ($channel) {
            self::EMAIL_CHANNEL => self::EMAIL_CHANNEL_STR,
            self::SMS_CHANNEL => self::SMS_CHANNEL_STR,
            self::LETTER_CHANNEL => self::LETTER_CHANNEL_STR,
            default => self::EMAIL_CHANNEL_STR,
        };
    }

    /**
     * @return string[]
     */
    public static function getChannels(): array
    {
        return [
            self::EMAIL_CHANNEL_STR,
            self::SMS_CHANNEL_STR,
            self::LETTER_CHANNEL_STR,
        ];
    }

    //
    // Setters
    //

    public function setChannel(string $channel): void
    {
        $this->channel = self::getChannelId($channel);
    }

    public function markSent(): void
    {
        $this->sent = true;
        $this->setSentAt(CarbonImmutable::now());
    }

    public function markFailed(string $detail): self
    {
        $this->failed = true;
        $this->failure_detail = $detail;

        return $this;
    }

    public function setSendAfter(CarbonImmutable $sendAfter): void
    {
        $this->send_after = $sendAfter->toDateTimeString();
    }

    public function setSentAt(CarbonImmutable $sentAt): void
    {
        $this->sent_at = $sentAt->toDateTimeString();
    }
}
