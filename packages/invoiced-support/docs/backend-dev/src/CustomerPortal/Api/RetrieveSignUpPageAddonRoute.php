<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\SignUpPageAddon;

/**
 * @extends AbstractRetrieveModelApiRoute<SignUpPageAddon>
 */
class RetrieveSignUpPageAddonRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: SignUpPageAddon::class,
        );
    }
}
