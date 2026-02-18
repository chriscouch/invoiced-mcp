<?php

namespace App\Integrations\Xero\Extractors;

use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Core\Orm\Model;
use stdClass;

abstract class AbstractXeroExtractor implements ExtractorInterface
{
    public function __construct(
        protected XeroApi $xeroApi,
    ) {
    }

    /**
     * Returns the object type as referred to by Invoiced.
     */
    abstract protected function xeroObjectType(): string;

    /**
     * @param XeroAccount $account
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->xeroApi->setAccount($account);
    }

    public function getObject(string $objectId): AccountingJsonRecord
    {
        return new AccountingJsonRecord($this->getXeroObject($objectId));
    }

    protected function getXeroObject(string $objectId): stdClass
    {
        try {
            return $this->xeroApi->get($this->xeroObjectType(), $objectId);
        } catch (IntegrationApiException $e) {
            throw new ExtractException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
