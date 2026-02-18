<?php

namespace App\PaymentProcessing\Api\Charges;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Charge;

/**
 * @extends AbstractRetrieveModelApiRoute<Charge>
 */
class RetrieveChargeRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Charge::class,
            features: ['accounts_receivable'],
        );
    }
}
