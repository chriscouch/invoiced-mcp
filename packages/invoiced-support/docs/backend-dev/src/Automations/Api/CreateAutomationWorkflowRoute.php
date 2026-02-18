<?php

namespace App\Automations\Api;

use App\Automations\Models\AutomationWorkflow;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Utils\Enums\ObjectType;
use RuntimeException;

/**
 * @extends AbstractCreateModelApiRoute<AutomationWorkflow>
 */
class CreateAutomationWorkflowRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'description' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'object_type' => new RequestParameter(
                    required: true,
                    types: ['string'],
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

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;

        try {
            $requestParameters['object_type'] = ObjectType::fromTypeName($requestParameters['object_type']);
        } catch (RuntimeException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        $context = $context->withRequestParameters($requestParameters);

        return parent::buildResponse($context);
    }
}
