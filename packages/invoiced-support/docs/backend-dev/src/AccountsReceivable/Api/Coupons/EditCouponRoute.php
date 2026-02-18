<?php

namespace App\AccountsReceivable\Api\Coupons;

use App\AccountsReceivable\Models\Coupon;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<Coupon>
 */
class EditCouponRoute extends AbstractEditModelApiRoute
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
                'expiration_date' => new RequestParameter(),
                'max_redemptions' => new RequestParameter(),
                'exclusive' => new RequestParameter(),
                'duration' => new RequestParameter(),
                'metadata' => new RequestParameter(),
            ],
            requiredPermissions: ['catalog.edit'],
            modelClass: Coupon::class,
            features: ['accounts_receivable'],
        );
    }
}
