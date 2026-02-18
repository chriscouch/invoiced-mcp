<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentLink;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<PaymentLink>
 */
class ListPaymentLinksRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: PaymentLink::class,
            features: ['payment_links'],
        );
    }
}
