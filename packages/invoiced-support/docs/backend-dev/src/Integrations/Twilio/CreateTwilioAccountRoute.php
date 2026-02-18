<?php

namespace App\Integrations\Twilio;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractCreateModelApiRoute<TwilioAccount>
 */
class CreateTwilioAccountRoute extends AbstractCreateModelApiRoute
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
