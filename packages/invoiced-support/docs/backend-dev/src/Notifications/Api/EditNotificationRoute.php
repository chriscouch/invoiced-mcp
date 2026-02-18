<?php

namespace App\Notifications\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Notifications\Models\Notification;

/**
 * @extends AbstractEditModelApiRoute<Notification>
 */
class EditNotificationRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: Notification::class,
        );
    }
}
