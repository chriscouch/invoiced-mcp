<?php

namespace App\SubscriptionBilling\Api;

use App\AccountsReceivable\Api\AbstractListLineItemsRoute;
use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\PendingLineItem;

class ListPendingLineItemsRoute extends AbstractListLineItemsRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: PendingLineItem::class,
            filterableProperties: ['customer_id'],
            features: ['metered_billing'],
        );
    }

    public function getParentClass(): string
    {
        return Customer::class;
    }

    public function getParentPropertyName(): string
    {
        return 'customer_id';
    }
}
