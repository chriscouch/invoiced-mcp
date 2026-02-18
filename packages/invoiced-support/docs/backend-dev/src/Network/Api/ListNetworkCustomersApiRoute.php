<?php

namespace App\Network\Api;

use App\Companies\Models\Company;
use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\Network\Models\NetworkConnection;
use App\Network\Traits\NetworkConnectionApiTrait;

/**
 * @extends AbstractListModelsApiRoute<NetworkConnection>
 */
class ListNetworkCustomersApiRoute extends AbstractListModelsApiRoute
{
    use NetworkConnectionApiTrait;

    public function __construct(
        private TenantContext $tenant,
        ApiCache $apiCache
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: NetworkConnection::class,
            features: ['network'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);
        $query->join(Company::class, 'customer_id', 'Companies.id')
            ->sort('Companies.name ASC');

        return $query;
    }

    public function parseFilterInput(ApiCallContext $context, array $input): ListFilter
    {
        $filter = parent::parseFilterInput($context, $input);

        return $filter->with(
            new FilterCondition(
                operator: FilterOperator::Equal,
                field: 'vendor_id',
                value: $this->tenant->get(),
            )
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var NetworkConnection[] $connections */
        $connections = parent::buildResponse($context);

        $result = [];
        foreach ($connections as $connection) {
            $result[] = $this->buildConnectionArray($connection, $connection->customer);
        }

        return $result;
    }
}
