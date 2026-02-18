<?php

namespace App\Companies\Api;

use App\Companies\Models\Member;
use App\Core\Files\Models\CustomerPortalAttachment;
use App\Core\Orm\Query;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<Member>
 */
class ListCustomerPortalAttachmentsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CustomerPortalAttachment::class,
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        return $query->with('file');
    }
}
