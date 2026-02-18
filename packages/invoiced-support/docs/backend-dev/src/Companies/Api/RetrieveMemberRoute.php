<?php

namespace App\Companies\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Member>
 */
class RetrieveMemberRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Member::class,
        );
    }
}
