<?php

namespace App\Webhooks\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Webhooks\Models\Webhook;

/**
 * @extends AbstractDeleteModelApiRoute<Webhook>
 */
class DeleteWebhookRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['business.admin'],
            modelClass: Webhook::class,
            features: ['api'],
        );
    }
}
