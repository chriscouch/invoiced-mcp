<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CreditBalanceAdjustment;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

class CreateCreditBalanceAdjustmentRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'amount' => new RequestParameter(
                    required: true,
                    types: ['numeric'],
                ),
                'customer' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'currency' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'date' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'notes' => new RequestParameter(
                    types: ['string', 'null'],
                ),
            ],
            requiredPermissions: ['payments.create'],
            modelClass: CreditBalanceAdjustment::class,
            features: ['accounts_receivable'],
        );
    }
}
