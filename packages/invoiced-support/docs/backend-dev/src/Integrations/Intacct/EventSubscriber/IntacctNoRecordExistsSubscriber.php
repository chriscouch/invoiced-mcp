<?php

namespace App\Integrations\Intacct\EventSubscriber;

use App\ActivityLog\ValueObjects\IntacctWriteFailureEvent;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber which attempts to resolve IntegrationApiExceptions of the type:
 * 'DL03000005 No record exits for the invoicekey xxxxxx'.
 */
class IntacctNoRecordExistsSubscriber implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private IntacctApi $client)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            IntacctWriteFailureEvent::class => 'onIntacctWriteFailure',
        ];
    }

    public function onIntacctWriteFailure(IntacctWriteFailureEvent $event): void
    {
        // check if updating the invoice mapping can resolve the exception
        $invoiceKey = static::parseInvoiceKey($event->getException());
        if (!$invoiceKey) {
            return;
        }

        // attempt to update invoice mapping
        $event->stopPropagation();
        $this->client->setAccount($event->getAccount());

        try {
            $this->updateInvoiceMapping($invoiceKey);
        } catch (IntegrationApiException $e) {
            $this->logger->warning('Unable to resolve IntegrationApiException', ['exception' => $e]);
        }
    }

    /**
     * Finds a mapped order entry invoice on Intacct and uses its PRRECORDKEY to
     * update the existing mapping.
     *
     * @param string $invoiceKey current mapping of the invoice
     *
     * @throws IntegrationApiException
     */
    public function updateInvoiceMapping(string $invoiceKey): void
    {
        // Attempt to find the corresponding invoice on Invoiced. This
        // may not necessarily exist because there are invoices that could
        // be exluded from our integration, or the invoice key pulled from
        // error message was not referencing an invoice but some other record type.
        $mapping = AccountingInvoiceMapping::findForAccountingId(IntegrationType::Intacct, $invoiceKey);
        if (!($mapping instanceof AccountingInvoiceMapping)) {
            return;
        }

        // find Intacct invoice and update mapping
        $invoice = $mapping->invoice;
        $metadata = $invoice->metadata;
        $documentType = $metadata->intacct_document_type ?? null;
        if ($documentType) {
            $mapping->accounting_id = $this->client->getOrderEntryTransactionPrRecordKey($documentType, $invoice->number);
            $mapping->save();
        }
    }

    /**
     * Parses invoice key from exception message of format
     * 'DL03000005 No record exits for the invoicekey xxxxxx'.
     * Returns null if cannot parse invoice key.
     */
    public static function parseInvoiceKey(IntegrationApiException $exception): ?string
    {
        $errorMessage = $exception->getMessage();

        // get substring starting at the invoice key
        // i.e 'xxxxx [Support ID: xxxxx]'
        $locator = 'invoicekey ';
        $index = strpos($errorMessage, $locator) + strlen($locator);
        $substr = substr($errorMessage, $index);

        // match the id from the substring
        $matched = preg_match('/[0-9]+/', $substr, $matches);
        if (!$matched) {
            return null;
        }

        return $matches[0];
    }
}
