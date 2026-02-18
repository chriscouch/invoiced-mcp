<?php

namespace App\Companies\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractEditModelApiRoute<Member>
 */
class EditMemberRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['business.admin'],
            modelClass: Member::class,
        );
    }
}
