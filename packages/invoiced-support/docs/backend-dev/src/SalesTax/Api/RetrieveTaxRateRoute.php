<?php

namespace App\SalesTax\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Models\TaxRate;

/**
 * @extends AbstractRetrieveModelApiRoute<TaxRate>
 */
class RetrieveTaxRateRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: TaxRate::class,
            features: ['accounts_receivable'],
        );
    }
}
