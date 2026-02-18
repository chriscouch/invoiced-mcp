<?php

namespace App\Integrations\Flywire\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Flywire\Models\FlywireDisbursement;

/**
 * @extends AbstractListModelsApiRoute<FlywireDisbursement>
 */
class ListFlywireDisbursementsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: FlywireDisbursement::class,
        );
    }
}
