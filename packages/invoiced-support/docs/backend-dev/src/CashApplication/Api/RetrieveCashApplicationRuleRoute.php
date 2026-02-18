<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationRule;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<CashApplicationRule>
 */
class RetrieveCashApplicationRuleRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: CashApplicationRule::class,
            features: ['accounts_receivable'],
        );
    }
}
