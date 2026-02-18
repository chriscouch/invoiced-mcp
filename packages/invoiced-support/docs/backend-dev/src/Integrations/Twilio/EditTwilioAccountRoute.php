<?php

namespace App\Integrations\Twilio;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<TwilioAccount>
 */
class EditTwilioAccountRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'account_sid' => new RequestParameter(),
                'auth_token' => new RequestParameter(),
                'from_number' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: TwilioAccount::class,
        );
    }
}
