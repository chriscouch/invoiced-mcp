<?php

namespace App\Integrations\Flywire\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Flywire\Models\FlywireRefundBundle;

/**
 * @extends AbstractRetrieveModelApiRoute<FlywireRefundBundle>
 */
class RetrieveFlywireRefundBundleRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: FlywireRefundBundle::class,
        );
    }
}
