<?php

namespace App\AccountsReceivable\Models;

use App\Chasing\InvoiceChasing\InvoiceChaseScheduleInspector;
use App\Chasing\InvoiceChasing\InvoiceChaseScheduleValidator;
use App\Chasing\InvoiceChasing\InvoiceChaseTimelineBuilder;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Chasing\ValueObjects\InvoiceChaseSchedule;
use App\Chasing\ValueObjects\InvoiceChaseStep;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Invoice level invoice chasing cadence configuration.
 *
 * @property int         $id
 * @property int         $invoice_id
 * @property Invoice     $invoice
 * @property string|null $emails
 * @property array       $chase_schedule
 * @property bool        $processed
 * @property bool        $disabled
 * @property string|null $last_sent_email
 * @property string|null $last_sent_sms
 * @property string|null $last_sent_letter
 * @property int|null    $cadence_id
 */
class InvoiceDelivery extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'invoice_id' => new Property(
                in_array: false,
            ),
            'invoice' => new Property(
                in_array: false,
                belongs_to: Invoice::class,
            ),
            'emails' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'chase_schedule' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'processed' => new Property(
                type: Type::BOOLEAN,
                default: false,
                in_array: false,
            ),
            'disabled' => new Property(
                type: Type::BOOLEAN,
                default: false,
                in_array: true,
            ),
            'last_sent_email' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'last_sent_sms' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'last_sent_letter' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'cadence_id' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'checkFeatures']);
        self::saving([self::class, 'checkDisabled']);
        self::saving([self::class, 'checkInvoiceStatus']);
        self::saving([self::class, 'validateSchedule']);
        self::saving([self::class, 'processEmailChanges']);
        self::saving([self::class, 'processScheduleChanges']);
    }

    //
    // Hooks
    //

    /**
     * Checks that the tenant has the correct features enabled to use Invoice Chasing.
     */
    public static function checkFeatures(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $company = $model->invoice->tenant();
        if (!$company->features->has('smart_chasing') || !$company->features->has('invoice_chasing')) {
            throw new ListenerException('This feature is not available on your current plan. Please contact Invoiced Support.');
        }
    }

    /**
     * Checks if the $disabled property has been modified.
     */
    public static function checkDisabled(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->dirty('disabled')) {
            $model->processed = false;
        }
    }

    /**
     * Checks if the invoice status is open/closed and allows/prevents operations
     * based on the status.
     *
     * @throws ListenerException
     */
    public static function checkInvoiceStatus(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $invoice = $model->invoice;

        if ($model->dirty('chase_schedule', true) || $model->dirty('emails', true)) {
            // do not allow chasing cadence updates to closed invoices
            if ($invoice->paid || $invoice->closed || $invoice->voided || $invoice->date_bad_debt) {
                throw new ListenerException('The invoice chasing cadence cannot be edited because the invoice is closed');
            }
        }
    }

    /**
     * Validates the structure of the chase schedule.
     *
     * @throws ListenerException
     */
    public static function validateSchedule(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if ($model->dirty('chase_schedule', true)) {
            try {
                InvoiceChaseScheduleValidator::validate($model->chase_schedule);
            } catch (InvalidArgumentException $e) {
                throw new ListenerException('Invalid chase schedule: '.$e->getMessage());
            }
        }

        if ($model->cadence_id &&
            ($model->dirty('chase_schedule', true) ||
                $model->dirty('cadence_id', true))) {
            // validate schedule against that of cadence template
            $template = InvoiceChasingCadence::find($model->cadence_id);
            if (!($template instanceof InvoiceChasingCadence) || !$model->getChaseSchedule()->equals($template->getChaseSchedule())) {
                $model->cadence_id = null;
            }
        }
    }

    /**
     * Handles changes in the chase schedule.
     *
     * @throws ListenerException
     */
    public static function processScheduleChanges(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->dirty('chase_schedule', true)) {
            return;
        }

        // merge ids for schedule intersection
        $oldSchedule = $model->ignoreUnsaved()->getChaseSchedule();
        $newSchedule = $model->getChaseSchedule()->intersect($oldSchedule);

        try {
            $processed = InvoiceChaseScheduleInspector::inspect(
                $model,
                $oldSchedule,
                $newSchedule
            );
        } catch (InvalidArgumentException $e) {
            throw new ListenerException($e->getMessage());
        }

        // update the model properties
        $model->chase_schedule = $processed->toArrays();
        $model->processed = false;

        // sort schedule
        $timelineBuilder = new InvoiceChaseTimelineBuilder();
        $timeline = $timelineBuilder->build($model);
        $sorted = [];
        foreach ($timeline as $segment) {
            $sorted[] = $segment->getChaseStep();
        }
        $model->chase_schedule = (new InvoiceChaseSchedule($sorted))->toArrays();
    }

    /**
     * Validates emails, ensures they're unique and strips all whitespace.
     * Refreshed schedule on email value change.
     */
    public static function processEmailChanges(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        if (!$model->emails || !$model->dirty('emails', true)) {
            return;
        }

        $emails = [];
        $uncheckedEmails = explode(',', str_replace(' ', '', $model->emails));
        foreach ($uncheckedEmails as $uncheckedEmail) {
            // check if well-formed
            if (!filter_var($uncheckedEmail, FILTER_VALIDATE_EMAIL)) {
                throw new ListenerException("Invalid email address '$uncheckedEmail'");
            }

            // check if unique
            if (!in_array($uncheckedEmail, $emails)) {
                $emails[] = $uncheckedEmail;
            }
        }

        $model->emails = implode(',', $emails);

        // Though this condition is checked above, it needs
        // to be checked again after the emails have been processed.
        // E.g The schedule should not be refreshed if the
        // saved emails value is "a@test.com,b@test.com" and the
        // unsaved emails value before processing is "a@test.com, b@test.com".
        if ($model->dirty('emails', true)) {
            $model->processed = false;
        }
    }

    //
    // Getters
    //

    public function getEmails(): array
    {
        if (!$this->ignoreUnsaved()->emails) {
            return [];
        }

        return explode(',', $this->ignoreUnsaved()->emails);
    }

    public function getChaseSchedule(): InvoiceChaseSchedule
    {
        return InvoiceChaseSchedule::fromArrays($this->chase_schedule);
    }

    public function getLastSentEmail(): ?CarbonImmutable
    {
        if (!$this->last_sent_email) {
            return null;
        }

        return new CarbonImmutable($this->last_sent_email);
    }

    public function getLastSentSms(): ?CarbonImmutable
    {
        if (!$this->last_sent_sms) {
            return null;
        }

        return new CarbonImmutable($this->last_sent_sms);
    }

    public function getLastSentLetter(): ?CarbonImmutable
    {
        if (!$this->last_sent_letter) {
            return null;
        }

        return new CarbonImmutable($this->last_sent_letter);
    }

    /**
     * Returns a reference to use on a scheduled send for specific chasing step.
     */
    public static function getSendReference(InvoiceDelivery $delivery, InvoiceChaseStep $step): string
    {
        return 'delivery:'.$delivery->id().':'.$step->getId();
    }

    //
    // Setters
    //

    public function applyCadence(InvoiceChasingCadence $cadence): void
    {
        $this->cadence_id = $cadence->id;
        $this->chase_schedule = $cadence->chase_schedule;
    }

    public function setLastSentEmail(CarbonImmutable $sentAt): void
    {
        $this->last_sent_email = $sentAt->toDateTimeString();
    }

    public function setLastSentSms(CarbonImmutable $sentAt): void
    {
        $this->last_sent_sms = $sentAt->toDateTimeString();
    }

    public function setLastSentLetter(CarbonImmutable $sentAt): void
    {
        $this->last_sent_letter = $sentAt->toDateTimeString();
    }
}
