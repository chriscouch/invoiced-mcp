<?php

namespace App\Notifications\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Notifications\Models\Notification;

/**
 * @extends AbstractRetrieveModelApiRoute<Notification>
 */
class RetrieveNotificationRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Notification::class,
        );
    }
}
