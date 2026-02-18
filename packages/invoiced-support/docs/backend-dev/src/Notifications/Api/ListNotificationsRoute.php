<?php

namespace App\Notifications\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Notifications\Models\Notification;

/**
 * @extends AbstractListModelsApiRoute<Notification>
 */
class ListNotificationsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Notification::class,
            filterableProperties: ['user_id', 'medium'],
        );
    }
}
