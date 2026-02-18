<?php

namespace App\AccountsReceivable\Api\Coupons;

use App\AccountsReceivable\Models\Coupon;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<Coupon>
 */
class DeleteCouponRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['catalog.edit'],
            modelClass: Coupon::class,
            features: ['accounts_receivable'],
        );
    }
}
