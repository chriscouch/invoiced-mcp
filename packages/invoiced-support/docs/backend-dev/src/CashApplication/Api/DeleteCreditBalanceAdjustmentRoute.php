<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CreditBalanceAdjustment;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class DeleteCreditBalanceAdjustmentRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['payments.delete'],
            modelClass: CreditBalanceAdjustment::class,
            features: ['accounts_receivable'],
        );
    }
}
