<?php

namespace App\SubscriptionBilling\Api;

use App\AccountsReceivable\Api\AbstractEditLineItemRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Models\PendingLineItem;

class EditPendingLineItemRoute extends AbstractEditLineItemRoute
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

    public function getParentPropertyName(): string
    {
        return 'customer_id';
    }
}
