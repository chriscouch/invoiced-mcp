<?php

namespace App\Core\RestApi\SavedFilters\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\SavedFilters\Models\Filter;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use RuntimeException;

/**
 * Lists the filters allowed to user.
 */
class ListFiltersRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                [
                    'type' => new QueryParameter(
                        required: true,
                    ),
                ],
            ),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Filter::class,
            filterableProperties: ['type'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $requester = ACLModelRequester::get();

        if ($requester instanceof Member) {
            $query->where('(private = 0 OR creator = '.$requester->id().')');
        } else {
            $query->where('private', 0);
        }

        try {
            $type = ObjectType::fromTypeName($context->queryParameters['type'])->value;
        } catch (RuntimeException $e) {
            throw new InvalidRequest($e->getMessage());
        }
        $query->where('type', $type);

        return $query;
    }
}
