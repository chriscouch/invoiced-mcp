<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationWorkflow;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<AutomationWorkflow>
 */
class RetrieveAutomationWorkflowRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: AutomationWorkflow::class,
            features: ['automations'],
        );
    }
}
