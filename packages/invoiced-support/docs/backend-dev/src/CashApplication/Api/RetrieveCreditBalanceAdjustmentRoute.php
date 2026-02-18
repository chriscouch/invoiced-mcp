<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CreditBalanceAdjustment;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class RetrieveCreditBalanceAdjustmentRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CreditBalanceAdjustment::class,
            features: ['accounts_receivable'],
        );
    }
}
