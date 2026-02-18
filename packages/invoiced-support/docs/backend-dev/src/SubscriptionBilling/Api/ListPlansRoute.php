<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\Metadata\Api\ListModelsWithMetadataRoute;
use App\SubscriptionBilling\Models\Plan;

class ListPlansRoute extends ListModelsWithMetadataRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Plan::class,
            filterableProperties: ['interval', 'interval_count', 'archived'],
            features: ['subscription_billing'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $showArchived = (bool) $context->request->query->get('archived');
        if ($showArchived) {
            $query->where('archived', true);
        } else {
            $query->where('archived', false);
        }

        return $query;
    }
}
