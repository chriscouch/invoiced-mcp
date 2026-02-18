<?php

namespace App\Chasing\Api;

use App\Chasing\Models\LateFeeSchedule;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractCreateModelApiRoute<LateFeeSchedule>
 */
class CreateLateFeeScheduleRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: LateFeeSchedule::class,
            features: ['accounts_receivable'],
        );
    }
}
