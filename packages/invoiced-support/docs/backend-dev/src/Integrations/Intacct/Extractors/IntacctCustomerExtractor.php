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
use App\Core\Orm\Model;

class IntacctCustomerExtractor extends AbstractIntacctExtractor
{
    private bool $useTopLevel = true;

    /**
     * @param IntacctSyncProfile $syncProfile
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        parent::initialize($account, $syncProfile);
        $this->useTopLevel = $syncProfile->customer_top_level;
    }

    /**
     * Must be called AFTER initialize().
     */
    public function setUseTopLevel(bool $useTopLevel): void
    {
        $this->useTopLevel = $useTopLevel;
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

        if ($customerAddon = $syncProfile->customer_read_query_addon) {
            $queryParts[] = $customerAddon;
        }
        $query = implode(' AND ', $queryParts);

        try {
            $result = $this->intacctApi->getCustomers($this->useTopLevel, [], $query);
            AccountingSyncStatus::setMessage($syncProfile->getIntegrationType(), 'Syncing Customers from Intacct ('.number_format($result->getTotalCount()).' records)');
            yield from $this->resultToGenerator($result);
        } catch (IntegrationApiException $e) {
            throw new ExtractException('Could not retrieve customers from Intacct: '.$e->getMessage(), 0, $e);
        }
    }

    public function getObject(string $objectId): AccountingXmlRecord
    {
        try {
            return new AccountingXmlRecord(
                $this->intacctApi->getCustomer($objectId, $this->useTopLevel, [])
            );
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
