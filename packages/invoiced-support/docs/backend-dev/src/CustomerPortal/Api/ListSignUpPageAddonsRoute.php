<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\SignUpPageAddon;

/**
 * @extends AbstractListModelsApiRoute<SignUpPageAddon>
 */
class ListSignUpPageAddonsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: SignUpPageAddon::class,
            filterableProperties: ['sign_up_page_id'],
        );
    }
}
