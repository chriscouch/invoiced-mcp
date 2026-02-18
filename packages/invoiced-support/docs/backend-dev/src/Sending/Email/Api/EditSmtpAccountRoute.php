<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Sending\Email\Models\SmtpAccount;

/**
 * @extends AbstractEditModelApiRoute<SmtpAccount>
 */
class EditSmtpAccountRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'host' => new RequestParameter(),
                'username' => new RequestParameter(),
                'password' => new RequestParameter(),
                'port' => new RequestParameter(),
                'encryption' => new RequestParameter(),
                'auth_mode' => new RequestParameter(),
                'fallback_on_failure' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: SmtpAccount::class,
        );
    }
}
