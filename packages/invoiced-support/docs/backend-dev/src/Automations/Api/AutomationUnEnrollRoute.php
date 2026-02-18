<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationWorkflowEnrollment;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<AutomationWorkflowEnrollment>
 */
class AutomationUnEnrollRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: AutomationWorkflowEnrollment::class,
            features: ['automations'],
        );
    }
}
