<?php

namespace App\SalesTax\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Models\TaxRule;

/**
 * @extends AbstractDeleteModelApiRoute<TaxRule>
 */
class DeleteTaxRuleRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: TaxRule::class,
            features: ['accounts_receivable'],
        );
    }
}
