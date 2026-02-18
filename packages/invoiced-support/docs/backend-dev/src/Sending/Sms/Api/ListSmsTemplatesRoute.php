<?php

namespace App\Sending\Sms\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Sms\Models\SmsTemplate;

/**
 * @extends AbstractListModelsApiRoute<SmsTemplate>
 */
class ListSmsTemplatesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: SmsTemplate::class,
        );
    }
}
