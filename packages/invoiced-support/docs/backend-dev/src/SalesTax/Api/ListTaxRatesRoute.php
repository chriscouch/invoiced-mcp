<?php

namespace App\SalesTax\Api;

use App\AccountsReceivable\Api\Coupons\AbstractListRatesRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SalesTax\Models\TaxRate;

class ListTaxRatesRoute extends AbstractListRatesRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: TaxRate::class,
            filterableProperties: ['archived'],
            features: ['accounts_receivable'],
        );
    }
}
