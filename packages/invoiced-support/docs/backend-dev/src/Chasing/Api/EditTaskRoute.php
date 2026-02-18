<?php

namespace App\Chasing\Api;

use App\Chasing\Models\Task;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<Task>
 */
class EditTaskRoute extends AbstractEditModelApiRoute
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
            requiredPermissions: ['tasks.edit'],
            modelClass: Task::class,
        );
    }
}
