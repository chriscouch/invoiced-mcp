<?php

namespace App\SalesTax\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Models\TaxRate;

/**
 * @extends AbstractDeleteModelApiRoute<TaxRate>
 */
class DeleteTaxRateRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['catalog.edit'],
            modelClass: TaxRate::class,
            features: ['accounts_receivable'],
        );
    }
}
