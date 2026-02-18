<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\SignUpPage;

/**
 * @extends AbstractListModelsApiRoute<SignUpPage>
 */
class ListSignUpPagesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: SignUpPage::class,
        );
    }
}
