<?php

namespace App\Integrations\AccountingSync\Traits;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Integrations\AccountingSync\AccountingMappingFactory;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\WriteSync\AccountingWriteSpool;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;

trait AccountingApiParametersTrait
{
    private ?string $accountingId = null;
    private ?IntegrationType $accountingSystem = null;
    private string $accountingSource = AbstractMapping::SOURCE_ACCOUNTING_SYSTEM;

    /**
     * @throws InvalidRequest
     */
    public function parseRequestAccountingParameters(ApiCallContext $context): void
    {
        if (!isset($context->requestParameters['accounting_id']) || !isset($context->requestParameters['accounting_system'])) {
            return;
        }

        $this->accountingId = $context->requestParameters['accounting_id'];
        $accountingSystem = $context->requestParameters['accounting_system'];

        try {
            $this->accountingSystem = IntegrationType::fromString($accountingSystem);
        } catch (IntegrationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        if (isset($context->requestParameters['accounting_source'])) {
            $this->accountingSource = $context->requestParameters['accounting_source'];
        }

        // Disable accounting write of this model or any other model
        // when the model comes from the accounting system.
        $model = $this->model;
        if ($model instanceof AccountingWritableModelInterface) {
            $model->skipReconciliation();
            AccountingWriteSpool::disable();
        }
    }

    /**
     * @throws InvalidRequest
     */
    protected function createAccountingMapping(AccountingWritableModel $model): void
    {
        if (!$this->accountingId || !$this->accountingSystem) {
            return;
        }

        $mapping = AccountingMappingFactory::getInstance($model);
        if (null == $mapping) {
            throw new InvalidRequest('Object does not support accounting mappings');
        }

        // we will not create new mapping if one already exists
        $mapping->refresh();
        if ($mapping->persisted()) {
            return;
        }

        $mapping->setIntegration($this->accountingSystem);
        $mapping->accounting_id = $this->accountingId;
        $mapping->source = $this->accountingSource;
        $mapping->save();
        // we are intentionally not checking the result of the save operation
        // since we do not want to block the result of the main api operation
    }
}
