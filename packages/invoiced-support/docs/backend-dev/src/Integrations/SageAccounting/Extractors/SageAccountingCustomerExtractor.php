<?php

namespace App\Integrations\SageAccounting\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use Generator;

class SageAccountingCustomerExtractor extends AbstractSageAccountingExtractor
{
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        try {
            $params = [];
            if ($query->lastSynced) {
                $params['updated_or_created_since'] = $query->lastSynced->toIso8601String();
            }

            $result = $this->sageApi->getCustomers($this->account, $params);

            // TODO: pagination
            foreach ($result->{'$items'} as $object) {
                yield new AccountingJsonRecord($object);
            }
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
