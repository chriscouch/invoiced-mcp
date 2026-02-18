<?php

namespace App\Sending\Sms\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Sms\Models\SmsTemplate;

/**
 * @extends AbstractDeleteModelApiRoute<SmsTemplate>
 */
class DeleteSmsTemplateRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: SmsTemplate::class,
        );
    }
}
