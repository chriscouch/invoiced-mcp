<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\EmailTemplate;

class CreateEmailTemplateRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: EmailTemplate::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;
        if (!isset($requestParameters['id'])) {
            $requestParameters['id'] = $context->request->attributes->get('model_id');
            $context = $context->withRequestParameters($requestParameters);
        }

        return parent::buildResponse($context);
    }
}
