<?php

namespace App\PaymentProcessing\Api\Payouts;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Payout;

/**
 * @extends AbstractRetrieveModelApiRoute<Payout>
 */
class RetrievePayoutRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Payout::class,
            features: ['accounts_receivable'],
        );
    }
}
