<?php

namespace App\Integrations\Flywire\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Flywire\Models\FlywirePayment;

/**
 * @extends AbstractRetrieveModelApiRoute<FlywirePayment>
 */
class RetrieveFlywirePaymentRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: FlywirePayment::class,
        );
    }
}
