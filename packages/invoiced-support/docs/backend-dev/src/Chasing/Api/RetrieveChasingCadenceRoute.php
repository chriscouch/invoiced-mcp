<?php

namespace App\Chasing\Api;

use App\Chasing\Models\ChasingCadence;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<ChasingCadence>
 */
class RetrieveChasingCadenceRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: ChasingCadence::class,
            features: ['smart_chasing'],
        );
    }
}
