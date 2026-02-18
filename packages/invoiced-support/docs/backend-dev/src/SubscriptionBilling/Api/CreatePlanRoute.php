<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\SubscriptionBilling\Models\Plan;

/**
 * @extends AbstractCreateModelApiRoute<Plan>
 */
class CreatePlanRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'id' => new RequestParameter(),
                'name' => new RequestParameter(),
                'currency' => new RequestParameter(),
                'amount' => new RequestParameter(),
                'interval' => new RequestParameter(),
                'interval_count' => new RequestParameter(),
                'pricing_mode' => new RequestParameter(),
                'tiers' => new RequestParameter(),
                'description' => new RequestParameter(),
                'notes' => new RequestParameter(),
                'catalog_item' => new RequestParameter(),
                'metadata' => new RequestParameter(),
            ],
            requiredPermissions: ['catalog.edit'],
            modelClass: Plan::class,
            features: ['subscription_billing'],
        );
    }
}
