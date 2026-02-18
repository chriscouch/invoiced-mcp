<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\SignUpPage;

/**
 * @extends AbstractRetrieveModelApiRoute<SignUpPage>
 */
class RetrieveSignUpPageRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: SignUpPage::class,
        );
    }
}
