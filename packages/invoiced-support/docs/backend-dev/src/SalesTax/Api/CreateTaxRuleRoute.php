<?php

namespace App\SalesTax\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Models\TaxRule;

/**
 * @extends AbstractCreateModelApiRoute<TaxRule>
 */
class CreateTaxRuleRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: TaxRule::class,
            features: ['accounts_receivable'],
        );
    }
}
