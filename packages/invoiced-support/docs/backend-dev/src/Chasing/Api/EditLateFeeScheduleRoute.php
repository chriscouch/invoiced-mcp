<?php

namespace App\Chasing\Api;

use App\Chasing\Models\LateFeeSchedule;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractEditModelApiRoute<LateFeeSchedule>
 */
class EditLateFeeScheduleRoute extends AbstractEditModelApiRoute
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
