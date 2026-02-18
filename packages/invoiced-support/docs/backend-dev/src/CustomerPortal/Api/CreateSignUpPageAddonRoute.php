<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\SignUpPageAddon;

/**
 * @extends AbstractCreateModelApiRoute<SignUpPageAddon>
 */
class CreateSignUpPageAddonRoute extends AbstractCreateModelApiRoute
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
