<?php

namespace App\Chasing\Api;

use App\Chasing\Models\Task;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<Task>
 */
class DeleteTaskRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['tasks.delete'],
            modelClass: Task::class,
        );
    }
}
