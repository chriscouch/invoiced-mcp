<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\PaymentMethod;

/**
 * @extends AbstractListModelsApiRoute<PaymentMethod>
 */
class ListPaymentMethodsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: PaymentMethod::class,
            features: ['accounts_receivable'],
        );
    }
}
