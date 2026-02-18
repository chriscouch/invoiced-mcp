<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationRule;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractCreateModelApiRoute<CashApplicationRule>
 */
class CreateCashApplicationRuleRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: CashApplicationRule::class,
            features: ['accounts_receivable'],
        );
    }
}
