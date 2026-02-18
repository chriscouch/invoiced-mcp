<?php

namespace App\Reports\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Query;
use App\Reports\Models\Dashboard;

class ListDashboardsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Dashboard::class,
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $requester = ACLModelRequester::get();

        if ($requester instanceof Member) {
            $query->where('(private = 0 OR creator_id = '.$requester->id().')');
        } else {
            $query->where('private', 0);
        }

        return $query;
    }
}
