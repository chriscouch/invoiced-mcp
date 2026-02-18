<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\DisabledPaymentMethod;

/**
 * @extends AbstractDeleteModelApiRoute<DisabledPaymentMethod>
 */
class DeleteDisabledPaymentRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: [],
            modelClass: DisabledPaymentMethod::class,
            features: ['accounts_receivable'],
        );
    }
}
