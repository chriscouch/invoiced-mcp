<?php

namespace App\AccountsReceivable\Api\Coupons;

use App\AccountsReceivable\Models\Coupon;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class ListCouponsRoute extends AbstractListRatesRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Coupon::class,
            filterableProperties: ['archived'],
            features: ['accounts_receivable'],
        );
    }
}
