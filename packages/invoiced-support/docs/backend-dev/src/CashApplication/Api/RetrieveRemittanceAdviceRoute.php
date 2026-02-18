<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Payment;
use App\CashApplication\Models\RemittanceAdvice;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Payment>
 */
class RetrieveRemittanceAdviceRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: RemittanceAdvice::class,
            features: ['accounts_receivable'],
        );
    }
}
