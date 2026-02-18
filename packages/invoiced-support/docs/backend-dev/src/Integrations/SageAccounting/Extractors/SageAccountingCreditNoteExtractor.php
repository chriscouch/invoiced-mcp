<?php

namespace App\Integrations\SageAccounting\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use Generator;

class SageAccountingCreditNoteExtractor extends AbstractSageAccountingExtractor
{
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        try {
            $params = [
                // TODO status, exclude Draft
                // TODO balance
            ];
            if ($query->lastSynced) {
                $params['updated_or_created_since'] = $query->lastSynced->toIso8601String();
            }
            if ($query->startDate) {
                // TODO
//                $params['search']['date_min'] = $query->startDate->toDateString();
            }
            if ($query->endDate) {
                // TODO
//                $params['search']['date_max'] = $query->endDate->toDateString();
            }
            if ($query->openItemsOnly) {
                // TODO
//                $params['search']['paid'] = 'false';
            }

            $result = $this->sageApi->getCreditNotes($this->account, $params);

            // TODO: pagination
            foreach ($result->{'$items'} as $object) {
                yield new AccountingJsonRecord($object);
            }
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
