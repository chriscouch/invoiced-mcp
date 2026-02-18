<?php

namespace App\Companies\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

/**
 * @extends AbstractListModelsApiRoute<Member>
 */
class ListMembersRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Member::class,
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        Member::skipExpiredCheck();

        return parent::buildQuery($context)
            ->where('expires', 0);
    }
}
