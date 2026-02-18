<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\SignUpPage;

/**
 * @extends AbstractDeleteModelApiRoute<SignUpPage>
 */
class DeleteSignUpPageRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: SignUpPage::class,
        );
    }
}
