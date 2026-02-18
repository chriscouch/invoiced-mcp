<?php

namespace App\Sending\Mail\Libs;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Database\TransactionManager;
use App\Core\I18n\AddressFormatter;
use App\Sending\Libs\AbstractSendChannel;
use App\Sending\Mail\Exceptions\SendLetterException;
use App\Sending\Models\ScheduledSend;
use App\Sending\ValueObjects\ScheduledSendParameters;
use Carbon\CarbonImmutable;
use CommerceGuys\Addressing\Address;

class LetterSendChannel extends AbstractSendChannel
{
    public function __construct(
        private LetterSender $sender,
        private TransactionManager $transactionManager
    ) {
    }

    public function send(ScheduledSend $scheduledSend): void
    {
        // get document
        $document = $scheduledSend->getDocument();
        if (!($document instanceof Invoice)) {
            $scheduledSend->markFailed('Non-invoice documents are not supported by mail.')
                ->saveOrFail();

            return;
        }

        // prevent sending letters for paid invoices
        if ($document->status === 'paid') {
            $scheduledSend->markFailed('Invoice already paid, letter not sent.')
                ->saveOrFail();

            return;
        }

        // build addresses information
        $formatter = new AddressFormatter();
        $formatter->setFrom($document->tenant());
        $from = $formatter->buildAddress();
        $to = $this->buildTo($scheduledSend->getParameters(), $document, $formatter);

        // validate addresses
        if (!$from->getAddressLine1() || !$to->getAddressLine1()) {
            $scheduledSend->markFailed('Address information is missing.')
                ->saveOrFail();

            return;
        }

        $customer = $document->customer();

        try {
            $this->sender->send($customer, $document, $from, $to);
            $scheduledSend->markSent();
        } catch (SendLetterException $e) {
            $scheduledSend->markFailed($e->getMessage());
        }

        // update delivery last sent time
        $this->transactionManager->perform(function () use ($scheduledSend) {
            $scheduledSend->saveOrFail();
            if ($invoiceDelivery = $this->getInvoiceDelivery($scheduledSend)) {
                $invoiceDelivery->setLastSentLetter(CarbonImmutable::now());
                $invoiceDelivery->saveOrFail();
            }
        });
    }

    /**
     * Builds recipient address.
     *
     * NOTE: If $scheduledSend->parameters['to'] is null, the address returned
     * will be the customer's address.
     */
    public function buildTo(ScheduledSendParameters $parameters, ReceivableDocument $document, AddressFormatter $formatter): Address
    {
        $customer = $document->customer();
        $toParams = $parameters->getTo();
        if (!is_array($toParams)) {
            // use customer address
            $formatter->setTo($customer);

            return $formatter->buildAddress();
        }

        // build address from scheduled send's 'to' property
        $to = new Address();
        $to = $to->withGivenName($toParams['name'] ?? $customer->name)
            ->withAddressLine1($toParams['address1'] ?? '')
            ->withAdministrativeArea($toParams['state'] ?? '')
            ->withPostalCode($toParams['postal_code'] ?? '')
            ->withCountryCode($toParams['country'] ?? '');

        if (isset($toParams['address2'])) {
            $to = $to->withAddressLine2($toParams['address2']);
        }

        if (isset($toParams['city'])) {
            $to = $to->withLocality($toParams['city']);
        }

        return $to;
    }
}
