<?php

namespace App\Chasing\Api;

use App\Chasing\Models\ChasingCadence;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<ChasingCadence>
 */
class DeleteChasingCadenceRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: ChasingCadence::class,
            features: ['smart_chasing'],
        );
    }
}
