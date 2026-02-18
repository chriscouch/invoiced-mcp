<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CreditBalanceAdjustment;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class EditCreditBalanceAdjustmentRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['payments.edit'],
            modelClass: CreditBalanceAdjustment::class,
            features: ['accounts_receivable'],
        );
    }
}
