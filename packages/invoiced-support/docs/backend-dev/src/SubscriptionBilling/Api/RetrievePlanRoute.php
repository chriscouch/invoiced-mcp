<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\Plan;

/**
 * @extends AbstractRetrieveModelApiRoute<Plan>
 */
class RetrievePlanRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Plan::class,
            features: ['subscription_billing'],
        );
    }
}
