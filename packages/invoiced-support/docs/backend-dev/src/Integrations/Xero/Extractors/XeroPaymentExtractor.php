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

class XeroPaymentExtractor extends AbstractXeroExtractor
{
    protected function xeroObjectType(): string
    {
        return 'Payments';
    }

    /**
     * @param XeroSyncProfile $syncProfile
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        $headers = [];
        if ($lastSynced = $query->lastSynced) {
            $headers['If-Modified-Since'] = $lastSynced->toDateTimeLocalString();
        }

        $where = ['PaymentType=="ACCRECPAYMENT"'];
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
            $page = 1;
            do {
                $params['page'] = $page;
                $objects = $this->xeroApi->getMany('Payments', $params, $headers);
                foreach ($objects as $object) {
                    yield new AccountingJsonRecord($object);
                }
                ++$page;
            } while (0 < count($objects));
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param AccountingJsonRecord $accountingObject
     */
    public function getObjectId(AccountingRecordInterface $accountingObject): string
    {
        return $accountingObject->document->PaymentID;
    }
}
