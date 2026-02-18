<?php

namespace App\Webhooks\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Webhooks\Models\WebhookAttempt;

/**
 * @extends AbstractListModelsApiRoute<WebhookAttempt>
 */
class ListWebhookAttemptsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: WebhookAttempt::class,
            filterableProperties: ['event_id'],
        );
    }
}
