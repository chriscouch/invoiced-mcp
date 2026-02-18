<?php

namespace App\Integrations\Intacct\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingSyncStatus;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use Generator;
use Intacct\Xml\Response\Result;

class IntacctPaymentExtractor extends AbstractIntacctExtractor
{
    private const FIELDS = [
        'RECORDNO',
        'CUSTOMERID',
        'CUSTOMERNAME',
        'STATE',
        'CURRENCY',
        'RECEIPTDATE',
        'DOCNUMBER',
        'RECORDID',
        'PAYMENTTYPE',
        'INVOICES',
        'CREDITS',
        'AUWHENCREATED',
        'MEGAENTITYID',
    ];

    /**
     * @param IntacctSyncProfile $syncProfile
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        $queryParts = [];
        if ($lastSynced = $query->lastSynced) {
            $queryParts = $this->applyReadCursor($queryParts, $syncProfile, $lastSynced);
        }

        if ($date = $query->startDate) {
            $date = $date->format('m/d/Y');
            $queryParts[] = "RECEIPTDATE >= '$date'";
        }
        if ($date = $query->endDate) {
            $date = $date->format('m/d/Y');
            $queryParts[] = "RECEIPTDATE <= '$date'";
        }
        $query = implode(' AND ', $queryParts);

        $resultFn = function (Result $result) {
            // Collect the ID of all the records we need to retrieve
            $ids = [];
            foreach ($result->getData() as $intacctPayment) {
                $ids[] = (string) $intacctPayment->{'RECORDNO'};
            }

            // Fetch results 100 records at a time
            while (count($ids) > 0) {
                try {
                    $idsToSearch = array_splice($ids, 0, 100);
                    $result = $this->intacctApi->getPaymentsByIds($idsToSearch, self::FIELDS);
                    foreach ($result->getData() as $row) {
                        yield new AccountingXmlRecord($row);
                    }
                } catch (IntegrationApiException $e) {
                    throw new ExtractException($e->getMessage(), $e->getCode(), $e);
                }
            }
        };

        try {
            $result = $this->intacctApi->getPayments(['RECORDNO'], $query);
            AccountingSyncStatus::setMessage($syncProfile->getIntegrationType(), 'Syncing A/R Payments from Intacct ('.number_format($result->getTotalCount()).' records)');
            yield from $this->resultToGenerator($result, $resultFn);
        } catch (IntegrationApiException $e) {
            throw new ExtractException('Could not retrieve payments from Intacct: '.$e->getMessage(), 0, $e);
        }
    }

    public function getObject(string $objectId): AccountingXmlRecord
    {
        try {
            return new AccountingXmlRecord(
                $this->intacctApi->getPayment($objectId, self::FIELDS)
            );
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
