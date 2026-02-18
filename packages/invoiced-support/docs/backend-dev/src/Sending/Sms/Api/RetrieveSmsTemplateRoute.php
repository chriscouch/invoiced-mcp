<?php

namespace App\Sending\Sms\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Sms\Models\SmsTemplate;

/**
 * @extends AbstractRetrieveModelApiRoute<SmsTemplate>
 */
class RetrieveSmsTemplateRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: SmsTemplate::class,
        );
    }
}
