<?php

namespace App\Sending\Sms\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\I18n\Countries;
use App\Core\Templating\Exception\MustacheException;
use App\Core\Templating\Exception\RenderException;
use App\Core\Templating\MustacheRenderer;
use App\Core\Templating\TwigContext;
use App\Core\Templating\TwigRenderer;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\InfuseUtility as Utility;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Sending\Sms\Exceptions\SendSmsException;
use App\Sending\Sms\Interfaces\TransportInterface;
use App\Sending\Sms\Models\TextMessage;
use App\Sending\Sms\Transport\TwilioTransport;
use App\Statements\Libs\AbstractStatement;
use App\Themes\ValueObjects\PdfTheme;
use App\Core\Orm\ACLModelRequester;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends text messages to a client.
 */
class TextMessageSender
{
    // Texts can only be sent between 8am and 9pm
    const BEGIN_HOUR = 8;
    const END_HOUR = 20;
    const SENT_CACHE_PREFIX = ':sent_sms.';
    const DEBOUNCE_PERIOD = 86400;
    private array $locks = [];

    public function __construct(
        private Countries $countries,
        private EventSpool $eventSpool,
        private LockFactory $lockFactory,
        private TwilioTransport $transport,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Sends a text message to a customer.
     *
     * @param Invoice|AbstractStatement|null $document
     *
     * @throws SendSmsException $e
     *
     * @return TextMessage[]
     */
    public function send(Customer $customer, $document, array $to, string $messageTemplate, array $variables, ?string $templateEngine, ?int $time = null): array
    {
        $company = $customer->tenant();
        $company->useTimezone();
        if (!$company->features->has('sms')) {
            throw new SendSmsException('This account does not support sending text messages.');
        }

        $time ??= time();
        $hour = date('G', $time);
        if (!($hour >= self::BEGIN_HOUR && $hour <= self::END_HOUR)) {
            throw new SendSmsException('Text messages can only be sent between 8am and 9pm');
        }

        // build up the contact list
        $contacts = [];
        $locks = [];
        foreach ($to as $contact) {
            $country = $contact['country'] ?? null;
            if (!$country) {
                $country = $company->country;
            }

            if (!$country) {
                continue;
            }

            $toPhone = $this->getPhoneNumber($contact['phone'], $country);
            if (!$toPhone) {
                continue;
            }

            $locks[] = $this->acquireLock($company, $toPhone);

            $contacts[] = [
                'phone' => $toPhone,
                'name' => $contact['name'] ?? '',
            ];
        }

        if (0 == count($contacts)) {
            throw new SendSmsException('The customer did not have any SMS enabled phone numbers on file.');
        }

        try {
            // send each personalized message through the transport
            $adapter = $this->getTransport();
            $twigContext = $this->makeTwigContext($customer, $variables);

            $textMessages = [];
            foreach ($contacts as $contact) {
                $variables['contact_name'] = $contact['name'];
                $message = $this->render($messageTemplate, $variables, $templateEngine, $twigContext);

                $result = $adapter->send($company, $contact['phone'], $message);

                // save the text message
                $textMessages[] = $this->recordTextMessage($customer, $document, $contact['name'], $contact['phone'], $message, $result);
            }
        } catch (MustacheException|RenderException $e) {
            // when there is a send exception we want to
            // release any locks that were just acquired
            // since the recipient did not actually receive anything
            $this->releaseLocks($locks);

            throw new SendSmsException('Could not render SMS template due to a parsing error: '.$e->getMessage(), 0, $e);
        } catch (SendSmsException $e) {
            // when there is a send exception we want to
            // release any locks that were just acquired
            // since the recipient did not actually receive anything
            $this->releaseLocks($locks);

            throw $e;
        }

        return $textMessages;
    }

    public function getTransport(): TransportInterface
    {
        if (!isset($this->transport)) {
            $this->transport = new TwilioTransport();
        }

        return $this->transport;
    }

    /**
     * Acquires a lock to send to a given phone #.
     *
     * @throws SendSmsException
     */
    public function acquireLock(Company $company, string $phone): LockInterface
    {
        // debounce sending to this person
        $lock = $this->getLock($company, $phone);
        if (!$lock->acquire()) {
            throw new SendSmsException("Sorry, your message could not be sent to $phone because a similar message has already been sent in the last day");
        }

        return $lock;
    }

    /**
     * Gets the internationalized phone number.
     */
    public function getPhoneNumber(?string $phone, string $country): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = preg_replace('/[^\+\d]/', '', $phone);

        // if the phone is prefixed with a '+' then we will assume it's complete
        if ('+' == substr((string) $phone, 0, 1)) {
            return $phone;
        }

        // otherwise we are going to look up the country's
        // phone code and see if it's present in the phone #
        // if not present, then we will append it
        $country = $this->countries->get($country);
        if (!$country) {
            return null;
        }

        if (!isset($country['phone_code'])) {
            return null;
        }

        $countryCode = $country['phone_code'];
        $countryCodes = array_merge([$countryCode], $country['alternative_phone_codes'] ?? []);
        foreach ($countryCodes as $code) {
            if (str_starts_with((string) $phone, $code)) {
                return '+'.$phone;
            }
        }

        return '+'.$countryCode.$phone;
    }

    //
    // Private methods
    //

    /**
     * Gets the debounce lock for a recipient, sender, and template combo.
     */
    private function getLock(Company $company, string $phone): Lock
    {
        if (!isset($this->locks[$phone])) {
            $key = $this->getDebounceKey($company, $phone);

            $this->locks[$phone] = $this->lockFactory->createLock($key, self::DEBOUNCE_PERIOD, false);
        }

        return $this->locks[$phone];
    }

    /**
     * Releases the given locks.
     *
     * @param LockInterface[] $locks
     */
    private function releaseLocks(array $locks): void
    {
        foreach ($locks as $lock) {
            if ($lock->isAcquired()) {
                $lock->release();
            }
        }
    }

    /**
     * Generates the cache key for a sent message.
     */
    private function getDebounceKey(Company $company, string $phone): string
    {
        $key = self::SENT_CACHE_PREFIX;

        $id = [
            $company->id(),
            $phone,
        ];

        $key .= md5(implode(',', $id));

        return $key;
    }

    private function makeTwigContext(Customer $customer, array $variables): TwigContext
    {
        return new TwigContext(
            $customer->tenant(),
            $variables['currency'] ?? $customer->calculatePrimaryCurrency(),
            $customer->moneyFormat(),
            $this->translator,
        );
    }

    private function render(string $template, array $variables, ?string $engine, TwigContext $context): string
    {
        if (PdfTheme::TEMPLATE_ENGINE_TIWG == $engine) {
            try {
                return trim(TwigRenderer::get()->render($template, $variables, $context));
            } catch (RenderException $e) {
                throw new SendSmsException('Could not render email subject due to a parsing error: '.$e->getMessage(), 0, $e);
            }
        }

        // Mustache is the default rendering engine
        return MustacheRenderer::get()->render($template, $variables);
    }

    /**
     * Records a delivered text message.
     *
     * @param Invoice|AbstractStatement|null $document
     */
    private function recordTextMessage(Customer $customer, $document, string $contactName, string $phone, string $message, array $parameters): TextMessage
    {
        $id = strtolower(Utility::guid(false));
        $textMessage = new TextMessage();
        $textMessage->id = $id;
        $textMessage->contact_name = $contactName;
        $textMessage->to = $phone;
        $textMessage->message = $message;
        $requester = ACLModelRequester::get();
        if ($requester instanceof Member) {
            $textMessage->sent_by = $requester->user();
        }
        foreach ($parameters as $k => $v) {
            $textMessage->$k = $v;
        }
        if ($document instanceof Invoice) {
            $textMessage->related_to_type = ObjectType::Invoice->value;
            $textMessage->related_to_id = (int) $document->id();
        }

        $textMessage->saveOrFail();

        // record the event
        $associations = [
            ['customer', $customer->id()],
        ];

        if ($document instanceof Invoice) {
            $associations[] = ['invoice', $document->id()];
        }

        $pendingEvent = new PendingEvent(
            object: $textMessage,
            type: EventType::TextMessageSent,
            associations: $associations
        );
        $this->eventSpool->enqueue($pendingEvent);

        return $textMessage;
    }
}
