<?php

namespace App\Integrations\BusinessCentral\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingSyncStatus;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use Generator;

class BusinessCentralCustomerExtractor extends AbstractBusinessCentralExtractor
{
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        try {
            // TODO: pagination
            $filter = '';
            if ($query->lastSynced) {
                $filter .= 'lastModifiedDateTime ge '.$query->lastSynced->toIso8601String();
            }
            $objects = $this->businessCentralApi->getCustomers($this->account, array_filter([
                '$filter' => $filter,
            ]));

            AccountingSyncStatus::setMessage($syncProfile->getIntegrationType(), 'Syncing Customers from Business Central ('.number_format(count($objects)).' records)');

            foreach ($objects as $object) {
                yield new AccountingJsonRecord($object);
            }
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
