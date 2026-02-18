<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationWorkflow;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<AutomationWorkflow>
 */
class DeleteAutomationWorkflowRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: AutomationWorkflow::class,
            features: ['automations'],
        );
    }
}
