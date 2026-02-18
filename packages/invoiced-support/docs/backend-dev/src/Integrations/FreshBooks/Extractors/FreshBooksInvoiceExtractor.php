<?php

namespace App\Integrations\FreshBooks\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use Generator;

class FreshBooksInvoiceExtractor extends AbstractFreshBooksExtractor
{
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        try {
            $params = [
                'include[]' => 'lines',
                // TODO status, exclude Draft
                // TODO balance
                'search' => [],
            ];
            if ($query->lastSynced) {
                $params['search']['updated_min'] = $query->lastSynced->toDateString();
            }
            if ($query->startDate) {
                $params['search']['date_min'] = $query->startDate->toDateString();
            }
            if ($query->endDate) {
                $params['search']['date_max'] = $query->endDate->toDateString();
            }
            if ($query->openItemsOnly) {
                $params['search']['paid'] = 'false';
            }

            $result = $this->freshBooksApi->getInvoices($this->account, $params);

            // TODO: pagination
            foreach ($result->invoices as $object) {
                yield new AccountingJsonRecord($object);
            }
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
