<?php

namespace App\Notifications\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Notifications\Models\NotificationEventCompanySetting;

/**
 * @extends AbstractListModelsApiRoute<NotificationEventCompanySetting>
 */
class ListNotificationEventCompanySettingsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: NotificationEventCompanySetting::class,
        );
    }
}
