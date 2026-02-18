<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\Plan;

/**
 * @extends AbstractDeleteModelApiRoute<Plan>
 */
class DeletePlanRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['catalog.edit'],
            modelClass: Plan::class,
            features: ['subscription_billing'],
        );
    }
}
