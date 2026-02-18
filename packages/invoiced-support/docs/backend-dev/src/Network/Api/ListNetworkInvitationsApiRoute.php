<?php

namespace App\Network\Api;

use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\Multitenant\TenantContext;
use App\Network\Models\NetworkInvitation;

/**
 * @extends AbstractListModelsApiRoute<NetworkInvitation>
 */
class ListNetworkInvitationsApiRoute extends AbstractListModelsApiRoute
{
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
            modelClass: NetworkInvitation::class,
            filterableProperties: ['customer', 'vendor'],
            features: ['network_invitations'],
        );
    }

    public function parseFilterInput(ApiCallContext $context, array $input): ListFilter
    {
        $filter = parent::parseFilterInput($context, $input);

        return $filter->with(
            new FilterCondition(
                operator: FilterOperator::Equal,
                field: 'from_company_id',
                value: $this->tenant->get(),
            )
        );
    }
}
