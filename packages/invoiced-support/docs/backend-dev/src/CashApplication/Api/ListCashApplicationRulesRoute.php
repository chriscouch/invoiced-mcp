<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationRule;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

class ListCashApplicationRulesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CashApplicationRule::class,
            features: ['cash_application'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        // eager load if including customerName property (dashboard only)
        if ($this->isParameterIncluded($context, 'customerName')) {
            $query->with('customer');
        }

        return $query;
    }
}
