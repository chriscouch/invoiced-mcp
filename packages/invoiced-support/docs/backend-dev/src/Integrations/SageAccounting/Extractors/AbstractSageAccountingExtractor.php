<?php

namespace App\Integrations\SageAccounting\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\AccountingRecordInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\SageAccounting\SageAccountingApi;
use App\Integrations\OAuth\Models\OAuthAccount;
use App\Core\Orm\Model;

abstract class AbstractSageAccountingExtractor implements ExtractorInterface
{
    protected OAuthAccount $account;

    public function __construct(
        protected SageAccountingApi $sageApi,
    ) {
    }

    /**
     * @param OAuthAccount $account
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->account = $account;
    }

    public function getObject(string $objectId): AccountingJsonRecord
    {
        try {
            // TODO
//            $object = $this->sageApi->get('TODO', $objectId);
            $object = (object) [];

            return new AccountingJsonRecord($object);
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param AccountingJsonRecord $accountingObject
     */
    public function getObjectId(AccountingRecordInterface $accountingObject): string
    {
        return $accountingObject->document->id;
    }
}
