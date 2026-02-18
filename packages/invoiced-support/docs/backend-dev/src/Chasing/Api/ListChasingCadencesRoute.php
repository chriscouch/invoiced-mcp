<?php

namespace App\Chasing\Api;

use App\Chasing\Models\ChasingCadence;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<ChasingCadence>
 */
class ListChasingCadencesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: ChasingCadence::class,
            filterableProperties: ['paused'],
            features: ['smart_chasing'],
        );
    }
}
