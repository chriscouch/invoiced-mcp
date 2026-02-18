<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\SignUpPageAddon;

/**
 * @extends AbstractEditModelApiRoute<SignUpPageAddon>
 */
class EditSignUpPageAddonRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: SignUpPageAddon::class,
        );
    }
}
