<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationWorkflowVersion;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<AutomationWorkflowVersion>
 */
class RetrieveAutomationWorkflowVersionRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: AutomationWorkflowVersion::class,
            features: ['automations'],
        );
    }

    public function buildResponse(ApiCallContext $context): AutomationWorkflowVersion
    {
        $version = parent::buildResponse($context);

        if (!$version->automation_workflow_id != $context->request->attributes->get('workflow_id')) {
            throw $this->modelNotFoundError();
        }

        return $version;
    }
}
