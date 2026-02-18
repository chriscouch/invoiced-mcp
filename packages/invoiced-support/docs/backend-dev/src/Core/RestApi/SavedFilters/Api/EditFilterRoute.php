<?php

namespace App\Core\RestApi\SavedFilters\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\SavedFilters\Models\Filter;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\ACLModelRequester;

class EditFilterRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'private' => new RequestParameter(
                    types: ['bool'],
                ),
            ],
            requiredPermissions: [],
            modelClass: Filter::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): Filter
    {
        $filter = parent::retrieveModel($context);

        $requester = ACLModelRequester::get();
        if (!$requester instanceof Member) {
            return $filter;
        }

        if (null === $filter->creator && $requester->allowed('business.admin')) {
            return $filter;
        }

        if ($filter->creator == $requester->id) {
            return $filter;
        }

        throw $this->permissionError();
    }
}
