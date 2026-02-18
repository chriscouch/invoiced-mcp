<?php

namespace App\PaymentProcessing\Api\Refunds;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Refund;

/**
 * @extends AbstractRetrieveModelApiRoute<Refund>
 */
class RetrieveRefundRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Refund::class,
            features: ['accounts_receivable'],
        );
    }
}
