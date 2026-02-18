<?php

namespace App\SalesTax\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Models\TaxRule;

/**
 * @extends AbstractListModelsApiRoute<TaxRule>
 */
class ListTaxRulesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: TaxRule::class,
            features: ['accounts_receivable'],
        );
    }
}
