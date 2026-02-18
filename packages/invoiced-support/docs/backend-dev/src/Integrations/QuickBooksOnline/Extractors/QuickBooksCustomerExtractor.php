<?php

namespace App\Integrations\QuickBooksOnline\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use Generator;

class QuickBooksCustomerExtractor extends AbstractQuickBooksExtractor
{
    /**
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    public function getObjects(AccountingSyncProfile $syncProfile, ReadQuery $query): Generator
    {
        $queryParts = ['Active IN (true, false)'];
        if ($lastSynced = $query->lastSynced) {
            $sinceDate = $lastSynced->toAtomString();
            $queryParts[] = "MetaData.LastUpdatedTime > '$sinceDate'";
        }
        $query = implode(' AND ', $queryParts);

        try {
            $page = 1;
            do {
                $objects = $this->quickBooksApi->query(QuickBooksApi::CUSTOMER, $page, $query);
                foreach ($objects as $object) {
                    yield new AccountingJsonRecord($object);
                }
                ++$page;
            } while (0 < count($objects));
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function quickBooksObjectType(): string
    {
        return QuickBooksApi::CUSTOMER;
    }
}
