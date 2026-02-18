<?php

namespace App\Integrations\Xero\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Xero\Models\XeroSyncProfile;
use Generator;

class XeroBatchPaymentExtractor extends AbstractXeroExtractor
{
    protected function xeroObjectType(): string
    {
        return 'BatchPayments';
    }

    /**
     * @param XeroSyncProfile $syncProfile
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        // CAUTION: Xero's batch payments API does not support pagination like
        // every other Xero API list endpoint. As a result we can only load the
        // first page. Xero Developer Support confirmed that pagination was not
        // supported and that there were no plans at the time to add it. In order
        // to prevent this from being an infinite loop we will not load any additional
        // pages because the ?page parameter is ignored on this endpoint.

        $headers = [];
        if ($lastSynced = $query->lastSynced) {
            $headers['If-Modified-Since'] = $lastSynced->toDateTimeLocalString();
        }

        $where = ['Type=="RECBATCH"'];
        if ($date = $query->startDate) {
            $date = $date->format('Y, m, d');
            $where[] = "Date >= DateTime($date)";
        }
        if ($date = $query->endDate) {
            $date = $date->format('Y, m, d');
            $where[] = "Date <= DateTime($date)";
        }

        $params = [
            'where' => implode(' AND ', $where),
        ];

        try {
            $objects = $this->xeroApi->getMany('BatchPayments', $params, $headers);
            foreach ($objects as $object) {
                yield new AccountingJsonRecord($object);
            }
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param AccountingJsonRecord $accountingObject
     */
    public function getObjectId(AccountingRecordInterface $accountingObject): string
    {
        return $accountingObject->document->BatchPaymentID;
    }
}
