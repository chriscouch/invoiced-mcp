<?php

namespace App\Chasing\Api;

use App\Chasing\Models\Task;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractCreateModelApiRoute<Task>
 */
class CreateTaskRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'customer_id' => new RequestParameter(),
                'name' => new RequestParameter(),
                'action' => new RequestParameter(),
                'due_date' => new RequestParameter(),
                'user_id' => new RequestParameter(),
                'complete' => new RequestParameter(),
                'completed_by_user_id' => new RequestParameter(),
                'bill_id' => new RequestParameter(),
                'vendor_credit_id' => new RequestParameter(),
            ],
            requiredPermissions: ['tasks.create'],
            modelClass: Task::class,
        );
    }
}
