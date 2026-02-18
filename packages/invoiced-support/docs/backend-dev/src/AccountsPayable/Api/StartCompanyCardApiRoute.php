<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Models\CompanyCard;
use App\AccountsPayable\Operations\CreateCompanyCard;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractCreateModelApiRoute<CompanyCard>
 */
class StartCompanyCardApiRoute extends AbstractCreateModelApiRoute
{
    public function __construct(
        private CreateCompanyCard $create,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: CompanyCard::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        try {
            return $this->create->start($this->tenant->get());
        } catch (AccountsPayablePaymentException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
