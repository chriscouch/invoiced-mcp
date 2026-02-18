<?php

namespace App\Companies\Api;

use App\Companies\Models\Company;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractRetrieveModelApiRoute<Company>
 */
class RetrieveCompanyRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Company::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): Company
    {
        $company = parent::retrieveModel($context);

        // Validate tenant ID matches context
        if ($this->tenant->get()->id != $company->id) {
            throw $this->modelNotFoundError();
        }

        return $company;
    }
}
