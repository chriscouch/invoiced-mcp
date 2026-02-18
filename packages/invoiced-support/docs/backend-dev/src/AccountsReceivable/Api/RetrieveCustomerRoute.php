<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Customer>
 */
class RetrieveCustomerRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }
}
