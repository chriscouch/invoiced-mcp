<?php

namespace App\Sending\Sms\Libs;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Database\TransactionManager;
use App\Sending\Libs\AbstractSendChannel;
use App\Sending\Models\ScheduledSend;
use App\Sending\Sms\Exceptions\SendSmsException;
use App\Sending\ValueObjects\ScheduledSendParameters;
use Carbon\CarbonImmutable;

class SmsSendChannel extends AbstractSendChannel
{
    const DEFAULT_TEXT = '{{company_name}}: You have a new invoice {{invoice_number}} {{url}}';

    public function __construct(
        private TextMessageSender $sender,
        private TransactionManager $transactionManager
    ) {
    }

    public function send(ScheduledSend $scheduledSend): void
    {
        // get document
        $document = $scheduledSend->getDocument();
        if (!($document instanceof Invoice)) {
            $scheduledSend->markFailed('Non-invoice documents are not supported by SMS.')
                ->saveOrFail();

            return;
        }

        // build recipient list
        $parameters = $scheduledSend->getParameters();
        $to = $this->buildTo($parameters, $document);
        if (0 == count($to)) {
            $scheduledSend->markFailed('Contact information is missing.')
                ->saveOrFail();

            return;
        }

        // build variables
        $customer = $document->customer();
        $company = $document->getSendCompany();
        $variables = [
            'company_name' => $company->getDisplayName(),
            'customer_name' => $customer->name,
            'customer_number' => $customer->number,
            'invoice_number' => $document->number,
            'balance' => $document->balance,
            'total' => $document->total,
            'url' => $document->url,
        ];

        $message = $this->buildMessage($parameters);

        try {
            $this->sender->send($customer, $document, $to, $message, $variables, null);
            $scheduledSend->markSent();
        } catch (SendSmsException $e) {
            $scheduledSend->markFailed($e->getMessage());
        }

        // update delivery last sent time
        $this->transactionManager->perform(function () use ($scheduledSend) {
            $scheduledSend->saveOrFail();
            if ($invoiceDelivery = $this->getInvoiceDelivery($scheduledSend)) {
                $invoiceDelivery->setLastSentSms(CarbonImmutable::now());
                $invoiceDelivery->saveOrFail();
            }
        });
    }

    /**
     * Builds a list of contacts compatible w/ TextMessageSender which
     * the provided invoice should be delivered to.
     *
     * NOTE: If $scheduledSend->to is null, the contacts returned
     * will include all customer billing contacts that have
     * a phone number and sms enabled. This includes non-primary
     * contacts.
     */
    public function buildTo(ScheduledSendParameters $parameters, ReceivableDocument $document): array
    {
        $to = $parameters->getTo();
        if (is_array($to)) {
            return $to;
        }

        // build from billing contacts
        $billingContacts = $document
            ->customer()
            ->contacts(false);

        $smsContacts = [];
        foreach ($billingContacts as $contact) {
            if (!$contact->sms_enabled || !$contact->phone) {
                continue;
            }

            $smsContacts[] = [
                'name' => $contact->name,
                'phone' => $contact->phone,
                'country' => $contact->country,
            ];
        }

        return $smsContacts;
    }

    /**
     * Returns the message being sent via text.
     */
    public function buildMessage(ScheduledSendParameters $parameters): string
    {
        if ($message = $parameters->getMessage()) {
            return $message;
        }

        return self::DEFAULT_TEXT;
    }
}
