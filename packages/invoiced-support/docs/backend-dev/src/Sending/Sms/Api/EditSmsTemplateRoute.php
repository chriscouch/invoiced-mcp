<?php

namespace App\Sending\Sms\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Sending\Sms\Models\SmsTemplate;

/**
 * @extends AbstractEditModelApiRoute<SmsTemplate>
 */
class EditSmsTemplateRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(),
                'language' => new RequestParameter(),
                'message' => new RequestParameter(),
                'template_engine' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: SmsTemplate::class,
        );
    }
}
