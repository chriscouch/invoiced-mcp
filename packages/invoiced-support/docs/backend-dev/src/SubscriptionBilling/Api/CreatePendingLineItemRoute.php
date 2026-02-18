<?php

namespace App\SubscriptionBilling\Api;

use App\AccountsReceivable\Api\AbstractCreateLineItemRoute;
use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\PendingLineItem;

class CreatePendingLineItemRoute extends AbstractCreateLineItemRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['invoices.create'],
            modelClass: PendingLineItem::class,
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
