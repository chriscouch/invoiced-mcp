<?php

namespace App\Integrations\BusinessCentral\Extractors;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Files\Models\Attachment;
use App\Core\Orm\Model;
use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingSyncStatus;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use Generator;
use stdClass;

class BusinessCentralInvoiceExtractor extends AbstractBusinessCentralExtractor
{
    private bool $importPdfs = false;

    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        parent::initialize($account, $syncProfile);
        $this->importPdfs = $syncProfile->read_pdfs;
    }

    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        try {
            $filter = ["status ne 'Draft' and status ne 'In Review'"];
            if ($query->lastSynced) {
                $filter[] = 'lastModifiedDateTime ge '.$query->lastSynced->toIso8601String();
            }
            if ($query->startDate) {
                $filter[] = 'invoiceDate ge '.$query->startDate->toDateString();
            }
            if ($query->endDate) {
                $filter[] = 'invoiceDate le '.$query->endDate->toDateString();
            }
            if ($query->openItemsOnly) {
                $filter[] = "status ne 'Canceled' and status ne 'Paid' and status ne 'Corrective'";
            }
            $filter = implode(' and ', $filter);

            $objects = $this->businessCentralApi->getSalesInvoices($this->account, array_filter([
                '$filter' => $filter,
                '$expand' => 'salesInvoiceLines',
            ]));
            // TODO: pagination

            AccountingSyncStatus::setMessage($syncProfile->getIntegrationType(), 'Syncing Invoices from Business Central ('.number_format(count($objects)).' records)');

            foreach ($objects as $object) {
                yield $this->buildResult($object);
            }
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws IntegrationApiException
     */
    protected function buildResult(stdClass $object): AccountingJsonRecord
    {
        $pdf = $this->importPdfs ? $this->getPdf($object->id, $object->number) : null;

        return new AccountingJsonRecord($object, (object) ['pdf' => $pdf]);
    }

    private function getPdf(string $id, string $number): ?string
    {
        // If the invoice already exists and has a PDF then we are not going to reload the PDF.
        // This is because the it causes the lastModifiedDateTime property of the record on Business Central
        // to be updated. As a result, this will cause the document to be included on every
        // sync and significantly slow it down.
        $document = Invoice::where('number', $number)->oneOrNull();

        if ($document) {
            $pdfAttachment = Attachment::where('parent_type', $document->object)
                ->where('parent_id', $document)
                ->where('location', Attachment::LOCATION_PDF)
                ->count();

            if ($pdfAttachment > 0) {
                return null;
            }
        }

        try {
            return $this->businessCentralApi->getPdf($this->account, 'salesInvoices', $id);
        } catch (IntegrationApiException) {
            // Do not throw an exception when retrieving the PDF
            // because that would halt the entire sync. We'll instead
            // prefer the document to go without a PDF.
            return null;
        }
    }
}
