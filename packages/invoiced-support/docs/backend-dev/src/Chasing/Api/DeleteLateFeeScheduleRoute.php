<?php

namespace App\Chasing\Api;

use App\Chasing\Models\LateFeeSchedule;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<LateFeeSchedule>
 */
class DeleteLateFeeScheduleRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: LateFeeSchedule::class,
            features: ['accounts_receivable'],
        );
    }
}
