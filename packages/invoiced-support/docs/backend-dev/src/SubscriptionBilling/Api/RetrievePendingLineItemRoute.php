<?php

namespace App\SubscriptionBilling\Api;

use App\AccountsReceivable\Api\AbstractRetrieveLineItemRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\PendingLineItem;

class RetrievePendingLineItemRoute extends AbstractRetrieveLineItemRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: PendingLineItem::class,
            features: ['metered_billing'],
        );
    }

    public function getParentPropertyName(): string
    {
        return 'customer_id';
    }
}
