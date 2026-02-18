<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationWorkflow;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<AutomationWorkflow>
 */
class EditAutomationWorkflowRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(
                    types: ['string'],
                ),
                'description' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'current_version' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'draft_version' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'enabled' => new RequestParameter(
                    types: ['boolean'],
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: AutomationWorkflow::class,
            features: ['automations'],
        );
    }
}
