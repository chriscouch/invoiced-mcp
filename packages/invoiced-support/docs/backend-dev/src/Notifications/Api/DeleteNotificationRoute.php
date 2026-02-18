<?php

namespace App\Notifications\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Notifications\Models\Notification;

/**
 * @extends AbstractDeleteModelApiRoute<Notification>
 */
class DeleteNotificationRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: Notification::class,
        );
    }
}
