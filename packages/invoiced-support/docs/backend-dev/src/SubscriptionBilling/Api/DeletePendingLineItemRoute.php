<?php

namespace App\SubscriptionBilling\Api;

use App\AccountsReceivable\Api\AbstractDeleteLineItemRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\PendingLineItem;

class DeletePendingLineItemRoute extends AbstractDeleteLineItemRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['invoices.create'],
            modelClass: PendingLineItem::class,
            features: ['metered_billing'],
        );
    }

    public function getParentPropertyName(): string
    {
        return 'customer_id';
    }
}
