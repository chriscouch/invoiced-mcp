<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\DisabledPaymentMethod;

/**
 * @extends AbstractCreateModelApiRoute<DisabledPaymentMethod>
 */
class CreateDisabledPaymentMethodRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: [],
            modelClass: DisabledPaymentMethod::class,
            features: ['accounts_receivable'],
        );
    }
}
