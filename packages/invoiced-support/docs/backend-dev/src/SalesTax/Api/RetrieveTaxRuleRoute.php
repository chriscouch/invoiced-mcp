<?php

namespace App\SalesTax\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Models\TaxRule;

/**
 * @extends AbstractRetrieveModelApiRoute<TaxRule>
 */
class RetrieveTaxRuleRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: TaxRule::class,
            features: ['accounts_receivable'],
        );
    }
}
