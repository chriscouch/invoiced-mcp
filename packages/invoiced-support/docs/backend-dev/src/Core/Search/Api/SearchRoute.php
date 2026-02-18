<?php

namespace App\Core\Search\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Multitenant\TenantContext;
use App\Core\Search\Exceptions\SearchException;
use App\Core\Search\Libs\Search;
use App\Core\Utils\Enums\ObjectType;
use RuntimeException;

class SearchRoute extends AbstractApiRoute
{
    private const MAX_RESULTS = 1000;
    private const QUERY_MAX_LEN = 256;

    public function __construct(
        private Search $search,
        private TenantContext $tenant
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [
                'query' => new QueryParameter(
                    required: true,
                ),
                'per_page' => new QueryParameter(
                    default: 5,
                ),
                'type' => new QueryParameter(
                    default: null,
                ),
                '_' => new QueryParameter(
                    default: null,
                ),
            ],
            requestParameters: [],
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $query = mb_substr($context->queryParameters['query'], 0, self::QUERY_MAX_LEN, 'UTF-8');
        $type = $context->queryParameters['type'];
        $numResults = min(self::MAX_RESULTS, (int) $context->queryParameters['per_page']);

        $company = $this->tenant->get();
        $model = null;
        if ($type) {
            try {
                $model = ObjectType::fromTypeName($type)->modelClass();
            } catch (RuntimeException $e) {
                throw new InvalidRequest($e->getMessage());
            }
        }

        $driver = $this->search->getDriver($company);

        try {
            return $driver->search($company, $query, $model, $numResults);
        } catch (SearchException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
