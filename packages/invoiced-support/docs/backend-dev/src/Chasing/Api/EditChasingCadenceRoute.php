<?php

namespace App\Chasing\Api;

use App\Chasing\Models\ChasingCadence;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<ChasingCadence>
 */
class EditChasingCadenceRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(),
                'time_of_day' => new RequestParameter(),
                'frequency' => new RequestParameter(),
                'run_date' => new RequestParameter(),
                'run_days' => new RequestParameter(),
                'paused' => new RequestParameter(),
                'min_balance' => new RequestParameter(),
                'steps' => new RequestParameter(),
                'assignment_mode' => new RequestParameter(),
                'assignment_conditions' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: ChasingCadence::class,
            features: ['smart_chasing'],
        );
    }
}
