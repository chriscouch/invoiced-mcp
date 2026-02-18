<?php

namespace App\SalesTax\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\SalesTax\Models\TaxRate;

/**
 * @extends AbstractCreateModelApiRoute<TaxRate>
 */
class CreateTaxRateRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'id' => new RequestParameter(),
                'name' => new RequestParameter(),
                'currency' => new RequestParameter(),
                'value' => new RequestParameter(),
                'is_percent' => new RequestParameter(),
                'inclusive' => new RequestParameter(),
                'metadata' => new RequestParameter(),
            ],
            requiredPermissions: ['catalog.edit'],
            modelClass: TaxRate::class,
            features: ['accounts_receivable'],
        );
    }
}
