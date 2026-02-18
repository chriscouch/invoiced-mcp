<?php

namespace App\Integrations\AccountingSync\Api;

use App\Companies\Models\Company;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Loaders\AccountingLoaderFactory;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;

abstract class AbstractAccountingSyncRoute extends AbstractApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private AccountingLoaderFactory $loaderFactory)
    {
    }

    public function buildResponse(ApiCallContext $context): array
    {
        try {
            // Deserialize the request into an accounting record
            $record = static::transform($context->requestParameters, $this->tenant->get());

            // Load the record into Invoiced
            $result = $this->loaderFactory->get($record)->load($record);
        } catch (LoadException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // Return the loading result
        return [
            'action' => $result->getAction(),
            'object' => $result->getModel()?->toArray(),
        ];
    }

    public static function transform(array $input, Company $company, string $accountingSystem = ''): AbstractAccountingRecord
    {
        throw new InvalidRequest('Not implemented');
    }

    protected static function getAccountingSystemType(string $accountingSystem): IntegrationType
    {
        try {
            return IntegrationType::fromString($accountingSystem);
        } catch (IntegrationException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
