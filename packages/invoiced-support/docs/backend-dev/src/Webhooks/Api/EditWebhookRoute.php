<?php

namespace App\Webhooks\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Webhooks\Models\Webhook;

/**
 * @extends AbstractEditModelApiRoute<Webhook>
 */
class EditWebhookRoute extends AbstractEditModelApiRoute
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
