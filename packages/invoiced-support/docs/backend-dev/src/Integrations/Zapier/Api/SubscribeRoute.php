<?php

namespace App\Integrations\Zapier\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Webhooks\Models\Webhook;

class SubscribeRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Webhook::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $targetUrl = $context->request->request->get('target_url');
        $eventType = explode('/', (string) $context->request->request->get('event'));

        // set create parameters
        $context = $context->withRequestParameters([
            'url' => $targetUrl,
            'events' => $eventType,
            'protected' => true,
        ]);

        return parent::buildResponse($context);
    }
}
