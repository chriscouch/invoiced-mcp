<?php

namespace App\Integrations\QuickBooksOnline\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Core\Orm\Model;

abstract class AbstractQuickBooksExtractor implements ExtractorInterface
{
    public function __construct(
        protected QuickBooksApi $quickBooksApi
    ) {
    }

    /**
     * Returns the object type as referred to by Invoiced.
     */
    abstract protected function quickBooksObjectType(): string;

    /**
     * Used for testing.
     */
    public function setClient(QuickBooksApi $api): void
    {
        $this->quickBooksApi = $api;
    }

    /**
     * @param QuickBooksAccount $account
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->quickBooksApi->setAccount($account);
    }

    public function getObject(string $objectId): AccountingJsonRecord
    {
        try {
            $object = $this->quickBooksApi->get($this->quickBooksObjectType(), $objectId);

            return $this->buildResult($object);
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param AccountingJsonRecord $accountingObject
     */
    public function getObjectId(AccountingRecordInterface $accountingObject): string
    {
        return $accountingObject->document->Id;
    }

    public function buildResult(object $transaction): AccountingJsonRecord
    {
        return new AccountingJsonRecord($transaction);
    }
}
