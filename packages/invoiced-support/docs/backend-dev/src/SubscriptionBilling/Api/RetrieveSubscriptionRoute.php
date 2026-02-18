<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\Subscription;

/**
 * @extends AbstractRetrieveModelApiRoute<Subscription>
 */
class RetrieveSubscriptionRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Subscription::class,
            features: ['subscription_billing'],
        );
    }
}
