<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Transaction;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Transaction>
 */
class RetrieveTransactionRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Transaction::class,
            features: ['accounts_receivable'],
        );
    }
}
