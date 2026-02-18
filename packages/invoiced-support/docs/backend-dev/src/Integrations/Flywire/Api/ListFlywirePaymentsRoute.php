<?php

namespace App\Integrations\Flywire\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Flywire\Models\FlywirePayment;

/**
 * @extends AbstractListModelsApiRoute<FlywirePayment>
 */
class ListFlywirePaymentsRoute extends AbstractListModelsApiRoute
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
