<?php

namespace App\Integrations\Intacct\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingSyncStatus;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use Generator;
use Intacct\Xml\Response\Result;
use App\Core\Orm\Model;
use SimpleXMLElement;

class IntacctAdjustmentExtractor extends AbstractIntacctExtractor
{
    private const FIELDS = [
        'RECORDNO',
        'RECORDID',
        'CURRENCY',
        'CUSTOMERID',
        'CUSTOMERNAME',
        'DESCRIPTION',
        'STATE',
        'TOTALENTERED',
        'TRX_TOTALENTERED',
        'TOTALPAID',
        'TRX_TOTALPAID',
        'WHENCREATED',
        'WHENMODIFIED',
        'WHENPOSTED',
        'MEGAENTITYID',
    ];

    private const BILL_TO_FIELDS = [
        'BILLTOPAYTOKEY',
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

    private const LINE_FIELDS = [
        'AMOUNT',
        'ENTRYDESCRIPTION',
        'RECORDKEY',
        'TRX_AMOUNT',
    ];

    private bool $hasMultiCurrency;

    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        parent::initialize($account, $syncProfile);
        $this->hasMultiCurrency = $account->tenant()->features->has('multi_currency');
    }

    /**
     * @param IntacctSyncProfile $syncProfile
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        $queryParts = [];
        if ($lastSynced = $query->lastSynced) {
            $queryParts = $this->applyReadCursor($queryParts, $syncProfile, $lastSynced);
        }
        $queryParts[] = $this->hasMultiCurrency ? 'TRX_TOTALENTERED < 0' : 'TOTALENTERED < 0';

        $queryParts[] = 'STATE <> "Draft"';

        if ($adjustmentAddon = $syncProfile->ar_adjustment_read_query_addon) {
            $queryParts[] = $adjustmentAddon;
        }
        if ($date = $query->startDate) {
            $date = $date->format('m/d/Y');
            $queryParts[] = "WHENPOSTED >= '$date'";
        }
        if ($date = $query->endDate) {
            $date = $date->format('m/d/Y');
            $queryParts[] = "WHENPOSTED <= '$date'";
        }
        $query = implode(' AND ', $queryParts);

        $resultFn = function (Result $result) {
            foreach ($result->getData() as $transaction) {
                yield $this->buildResult($transaction);
            }
        };

        $fields = self::FIELDS;

        // add in bill to fields, if importing bill to contact as customer
        if (IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO == $syncProfile->customer_import_type) {
            $fields = array_merge($fields, self::BILL_TO_FIELDS);
        }

        try {
            $result = $this->intacctApi->getArAdjustments($fields, $query);
            AccountingSyncStatus::setMessage($syncProfile->getIntegrationType(), 'Syncing A/R Adjustments from Intacct ('.number_format($result->getTotalCount()).' records)');
            yield from $this->resultToGenerator($result, $resultFn);
        } catch (IntegrationApiException $e) {
            throw new ExtractException('Could not retrieve adjustments from Intacct: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return AccountingXmlRecord
     */
    public function getObject(string $objectId): AccountingRecordInterface
    {
        try {
            $adjustment = $this->intacctApi->getArAdjustment($objectId, self::FIELDS);

            return $this->buildResult($adjustment);
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param AccountingXmlRecord $accountingObject
     */
    public function getObjectId(object $accountingObject): string
    {
        return (string) $accountingObject->document->{'RECORDNO'};
    }

    /**
     * Gets the line items for an individual adjustment.
     *
     * @throws ExtractException
     *
     * @return SimpleXMLElement[]
     */
    private function getLines(string $id): array
    {
        try {
            $result = $this->intacctApi->getArAdjustmentLines($id, self::LINE_FIELDS);

            $resultFn = fn (Result $result) => yield from $result->getData();

            // We need preserve_keys=false because otherwise this will
            // only return the last page of results when used with
            // yield from. Source: https://www.php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
            return iterator_to_array($this->resultToGenerator($result, $resultFn), false);
        } catch (IntegrationApiException $e) {
            throw new ExtractException('Could not retrieve adjustment lines: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws ExtractException
     */
    private function buildResult(SimpleXMLElement $adjustment): AccountingXmlRecord
    {
        return new AccountingXmlRecord(
            document: $adjustment,
            lines: $this->getLines((string) $adjustment->{'RECORDNO'})
        );
    }
}
