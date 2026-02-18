<?php

namespace App\AccountsReceivable\Api\Coupons;

use App\AccountsReceivable\Models\Coupon;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Coupon>
 */
class RetrieveCouponRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Coupon::class,
            features: ['accounts_receivable'],
        );
    }
}
