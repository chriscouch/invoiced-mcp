<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationWorkflowEnrollment;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractCreateModelApiRoute<AutomationWorkflowEnrollment>
 */
class AutomationEnrollRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'workflow' => new RequestParameter(
                    required: true,
                    types: ['numeric'],
                ),
                'object_id' => new RequestParameter(
                    required: true,
                    types: ['numeric'],
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: AutomationWorkflowEnrollment::class,
            features: ['automations'],
        );
    }
}
