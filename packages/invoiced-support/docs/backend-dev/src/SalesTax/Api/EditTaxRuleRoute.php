<?php

namespace App\SalesTax\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Models\TaxRule;

/**
 * @extends AbstractEditModelApiRoute<TaxRule>
 */
class EditTaxRuleRoute extends AbstractEditModelApiRoute
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
