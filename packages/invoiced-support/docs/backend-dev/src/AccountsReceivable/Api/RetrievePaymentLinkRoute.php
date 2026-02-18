<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Customer>
 */
class RetrievePaymentLinkRoute extends AbstractRetrieveModelApiRoute
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
