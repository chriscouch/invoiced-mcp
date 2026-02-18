<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\CustomerPaymentBatch;

/**
 * @extends AbstractListModelsApiRoute<CustomerPaymentBatch>
 */
class ListCustomerPaymentBatchesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CustomerPaymentBatch::class,
            features: ['accounts_receivable'],
        );
    }
}
