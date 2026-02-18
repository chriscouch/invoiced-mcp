<?php

namespace App\Core\RestApi\SavedFilters\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\SavedFilters\Models\Filter;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\ACLModelRequester;
use App\Core\Utils\Enums\ObjectType;

class CreateFilterRoute extends AbstractCreateModelApiRoute
{
    private Member $requester;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'type' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'settings' => new RequestParameter(
                    required: true,
                ),
                'private' => new RequestParameter(
                    types: ['bool'],
                    default: false,
                ),
            ],
            requiredPermissions: [],
            modelClass: Filter::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $member = ACLModelRequester::get();
        if (!$member instanceof Member) {
            // no specific errors available, throw a server error
            throw new ApiError('There was an error creating the '.$this->getModelName().'.');
        }
        $this->requester = $member;
        $requestParameters = $context->requestParameters;
        $requestParameters['creator'] = $this->requester->id;
        $requestParameters['type'] = ObjectType::fromTypeName($requestParameters['type'])->value;
        $context = $context->withRequestParameters($requestParameters);

        return parent::buildResponse($context);
    }
}
