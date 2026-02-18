<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\CustomerPaymentBatch;

/**
 * @extends AbstractRetrieveModelApiRoute<CustomerPaymentBatch>
 */
class RetrieveCustomerPaymentBatchRoute extends AbstractRetrieveModelApiRoute
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
