<?php

namespace App\Automations\Api;

use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Exception\AutomationException;
use App\Automations\Models\AutomationRun;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\TriggerAutomation;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;

/**
 * @extends AbstractModelApiRoute<AutomationWorkflow>
 */
class TriggerAutomationRoute extends AbstractModelApiRoute
{
    public function __construct(private TriggerAutomation $triggerAutomation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'workflow' => new RequestParameter(
                    required: true,
                    types: ['int'],
                ),
                'object_type' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'object_id' => new RequestParameter(
                    required: true,
                    types: ['string', 'integer'],
                ),
            ],
            requiredPermissions: [],
            modelClass: AutomationWorkflow::class,
            features: ['automations'],
        );
    }

    public function buildResponse(ApiCallContext $context): AutomationRun
    {
        $workflow = $this->getModelOrFail(AutomationWorkflow::class, $context->requestParameters['workflow']);
        $objectType = ObjectType::fromTypeName($context->requestParameters['object_type']);
        $object = $this->getModelOrFail($objectType->modelClass(), $context->requestParameters['object_id']);
        if (!$object instanceof MultitenantModel) {
            throw new InvalidRequest('Object type not supported: '.$objectType->typeName());
        }

        try {
            return $this->triggerAutomation->initiate($workflow, $object, AutomationTriggerType::Manual);
        } catch (AutomationException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
