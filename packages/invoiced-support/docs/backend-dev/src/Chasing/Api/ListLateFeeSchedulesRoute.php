<?php

namespace App\Chasing\Api;

use App\Chasing\Models\LateFeeSchedule;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<LateFeeSchedule>
 */
class ListLateFeeSchedulesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: LateFeeSchedule::class,
            features: ['accounts_receivable'],
        );
    }
}
