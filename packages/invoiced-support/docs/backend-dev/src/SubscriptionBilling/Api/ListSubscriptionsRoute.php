<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Routes\AbstractListRoutesWithQueryBuilderRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\Subscription;

class ListSubscriptionsRoute extends AbstractListRoutesWithQueryBuilderRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Subscription::class,
            features: ['subscription_billing'],
        );
    }

    protected function getOptions(ApiCallContext $context): array
    {
        return [
            'filter' => $this->filter,
            'advanced_filter' => $context->queryParameters['advanced_filter'] ?? null,
            'sort' => $this->getSort(),
            'automation' => $context->queryParameters['automation'] ?? null,
            'plan' => $context->queryParameters['plan'] ?? null,
            'all' => $context->queryParameters['all'] ?? null,
            'canceled' => $context->queryParameters['canceled'] ?? null,
            'finished' => $context->queryParameters['finished'] ?? null,
            'contract' => $context->queryParameters['contract'] ?? null,
            'metadata' => $context->queryParameters['metadata'] ?? null,
        ];
    }
}
