<?php

namespace App\Webhooks\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Webhooks\Models\Webhook;

/**
 * @extends AbstractCreateModelApiRoute<Webhook>
 */
class CreateWebhookRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'url' => new RequestParameter(),
                'enabled' => new RequestParameter(),
                'events' => new RequestParameter(),
                'protected' => new RequestParameter(),
            ],
            requiredPermissions: ['business.admin'],
            modelClass: Webhook::class,
            features: ['api'],
        );
    }
}
