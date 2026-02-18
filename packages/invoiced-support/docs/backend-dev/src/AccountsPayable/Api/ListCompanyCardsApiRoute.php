<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\CompanyCard;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

/**
 * @extends AbstractListModelsApiRoute<CompanyCard>
 */
class ListCompanyCardsApiRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CompanyCard::class,
            filterableProperties: [],
            features: ['accounts_payable'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);
        $query->where('deleted', false);

        return $query;
    }
}
