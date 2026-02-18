<?php

namespace App\Webhooks\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Webhooks\Models\Webhook;

/**
 * @extends AbstractListModelsApiRoute<Webhook>
 */
class ListWebhooksRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['business.admin'],
            modelClass: Webhook::class,
            filterableProperties: ['protected'],
            features: ['api'],
        );
    }
}
