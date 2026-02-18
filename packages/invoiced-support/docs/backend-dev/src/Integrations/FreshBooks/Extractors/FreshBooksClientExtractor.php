<?php

namespace App\Integrations\FreshBooks\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use Generator;

class FreshBooksClientExtractor extends AbstractFreshBooksExtractor
{
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        try {
            $params = [];
            if ($query->lastSynced) {
                $params['updated_min'] = $query->lastSynced->toDateString();
            }

            $result = $this->freshBooksApi->getClients($this->account, $params);

            // TODO: pagination
            foreach ($result->clients as $object) {
                yield new AccountingJsonRecord($object);
            }
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
