<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<Customer>
 */
class DeleteCustomerRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['customers.delete'],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }
}
