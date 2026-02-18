<?php

namespace App\Integrations\Intacct\Extractors;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Files\Models\Attachment;
use App\Core\Orm\Model;
use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingSyncStatus;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctMapper;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use Generator;
use Intacct\Xml\Response\Result;
use SimpleXMLElement;

class IntacctOrderEntryTransactionExtractor extends AbstractIntacctExtractor
{
    private const FIELDS = [
        'RECORDNO',
        'PRRECORDKEY',
        'DOCNO',
        'CURRENCY',
        'STATE',
        'WHENPOSTED',
        'WHENDUE',
        'TRX_TOTALPAID',
        'TERM.NAME',
        'MESSAGE',
        'PONUMBER',
        'CONTRACTID',
        'SODOCUMENTENTRIES',
        'SUBTOTALS',
        // customers
        'CUSTREC',
        'CUSTVENDID',
        'CUSTVENDNAME',
        // ship to
        'SHIPTO.PRINTAS',
        'SHIPTO.MAILADDRESS.ADDRESS1',
        'SHIPTO.MAILADDRESS.ADDRESS2',
        'SHIPTO.MAILADDRESS.CITY',
        'SHIPTO.MAILADDRESS.STATE',
        'SHIPTO.MAILADDRESS.ZIP',
        'SHIPTO.MAILADDRESS.COUNTRYCODE',
        'MEGAENTITYID',
    ];

    private const BILL_TO_FIELDS = [
        'BILLTOKEY',
        'BILLTO.PRINTAS',
        'BILLTO.EMAIL1',
        'BILLTO.EMAIL2',
        'BILLTO.PHONE1',
        'BILLTO.MAILADDRESS.ADDRESS1',
        'BILLTO.MAILADDRESS.ADDRESS2',
        'BILLTO.MAILADDRESS.CITY',
        'BILLTO.MAILADDRESS.STATE',
        'BILLTO.MAILADDRESS.ZIP',
        'BILLTO.MAILADDRESS.COUNTRYCODE',
    ];

    private const SHIP_TO_DISTRIBUTION_FIELDS = [
        'SHIPTO.EMAIL1',
        'SHIPTO.EMAIL2',
    ];

    private string $documentType;
    private array $intacctFields;
    private array $transactionQuery;
    private bool $importPdfs = false;

    /**
     * @param IntacctSyncProfile $syncProfile
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        parent::initialize($account, $syncProfile);

        // determine fields to fetch
        $this->intacctFields = $this->getIntacctFields($syncProfile);

        // add in custom mapping and query settings
        $this->transactionQuery = [];
        $this->importPdfs = false;

        if ($queryAddonMap = $syncProfile->invoice_import_query_addon) {
            if (isset($queryAddonMap->{$this->documentType})) {
                $this->transactionQuery[] = $queryAddonMap->{$this->documentType};
            } elseif (isset($queryAddonMap->{'*'})) {
                $this->transactionQuery[] = $queryAddonMap->{'*'};
            }
        }

        $this->importPdfs = $syncProfile->read_pdfs;
    }

    /**
     * MUST be called BEFORE initialize().
     *
     * @param string $documentType document type, i.e. Sales Invoice
     */
    public function setDocumentType(string $documentType): void
    {
        $this->documentType = $documentType;
    }

    /**
     * Gets the Intacct field list that are needed to sync this record type.
     */
    public function getIntacctFields(IntacctSyncProfile $syncProfile): array
    {
        $fields = self::FIELDS;

        if ($mapping = $syncProfile->invoice_import_mapping) {
            $mapper = new IntacctMapper();
            $customMapping = $mapper->parseDocumentFieldMapping($mapping, $this->documentType);

            if ($customMapping) {
                foreach ((array) $customMapping as $source => $destination) {
                    $fields[] = $source;
                }
            }
        }

        // add in bill to fields, if importing bill to contact as customer
        if (IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO == $syncProfile->customer_import_type) {
            $fields = array_merge($fields, self::BILL_TO_FIELDS);
        }

        // add in custom distribution ship to fields
        if ($syncProfile->ship_to_invoice_distribution_list) {
            $fields = array_merge($fields, self::SHIP_TO_DISTRIBUTION_FIELDS);
        }

        return array_unique($fields);
    }

    /**
     * @param IntacctSyncProfile $syncProfile
     *
     * @return Generator<AccountingXmlRecord>
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        $queryParts = $this->transactionQuery;
        if ($lastSynced = $query->lastSynced) {
            $queryParts = $this->applyReadCursor($queryParts, $syncProfile, $lastSynced);
        }
        if ($date = $query->startDate) {
            $date = $date->format('m/d/Y');
            $queryParts[] = "WHENPOSTED >= '$date'";
        }
        if ($date = $query->endDate) {
            $date = $date->format('m/d/Y');
            $queryParts[] = "WHENPOSTED <= '$date'";
        }
        if ($query->openItemsOnly) {
            $queryParts[] = 'TRX_TOTALDUE > 0 AND TRX_TOTALENTERED > 0';
        }
        $query = implode(' AND ', $queryParts);

        $resultFn = function (Result $result) use ($syncProfile) {
            // Collect the ID of all the records we need to retrieve
            $ids = [];
            foreach ($result->getData() as $intacctTransaction) {
                $ids[] = (string) $intacctTransaction->{'RECORDNO'};
            }

            // Fetch results 100 records at a time
            $batchSize = $syncProfile->read_batch_size ?: 100;
            while (count($ids) > 0) {
                try {
                    $idsToSearch = array_splice($ids, 0, $batchSize);
                    $result = $this->intacctApi->getOrderEntryTransactionsByIds($this->documentType, $idsToSearch, $this->intacctFields);
                    foreach ($result->getData() as $transaction) {
                        yield $this->buildResult($transaction);
                    }
                } catch (IntegrationApiException $e) {
                    throw new ExtractException($e->getMessage(), $e->getCode(), $e);
                }
            }
        };

        try {
            $result = $this->intacctApi->getOrderEntryTransactions($this->documentType, ['RECORDNO'], $query);
            AccountingSyncStatus::setMessage($syncProfile->getIntegrationType(), 'Syncing Order Entry Transactions ('.$this->documentType.') from Intacct ('.number_format($result->getTotalCount()).' records)');
            yield from $this->resultToGenerator($result, $resultFn);
        } catch (IntegrationApiException $e) {
            throw new ExtractException('Could not retrieve order entry transactions from Intacct: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return AccountingXmlRecord
     */
    public function getObject(string $objectId): AccountingRecordInterface
    {
        try {
            $transaction = $this->intacctApi->getOrderEntryTransaction($this->documentType, $objectId, $this->intacctFields);

            return $this->buildResult($transaction);
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws ExtractException
     */
    public function buildResult(SimpleXMLElement $transaction): AccountingXmlRecord
    {
        $pdf = $this->importPdfs ? $this->getPdf($this->documentType, (string) $transaction->{'DOCNO'}) : null;

        return new AccountingXmlRecord(
            document: $transaction,
            pdf: $pdf
        );
    }

    /**
     * Retrieves the document PDF from Intacct.
     */
    private function getPdf(string $documentType, string $number): ?string
    {
        // If the invoice already exists and has a PDF then we are not going to reload the PDF.
        // This is because the retrievepdf option is expensive and significantly slows down
        // the sync in the case of a large number of records. The downside of this approach
        // is that an invoice PDF can change which would not be retrieved by the integration.
        // The solution in that case is to remove the PDF from the invoice and re-sync it.
        $document = Invoice::where('number', $number)->oneOrNull();
        // We don't know if the document will be an invoice or credit note so we have to check both.
        if (!$document) {
            $document = CreditNote::where('number', $number)->oneOrNull();
        }

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
            return $this->intacctApi->getOrderEntryPdf($documentType, $number);
        } catch (IntegrationApiException) {
            // Do not throw an exception when retrieving the PDF
            // because that would halt the entire sync. We'll instead
            // prefer the document to go without a PDF.
            return null;
        }
    }

    /**
     * @param AccountingXmlRecord $accountingObject
     */
    public function getObjectId(object $accountingObject): string
    {
        return (string) $accountingObject->document->{'RECORDNO'};
    }
}
