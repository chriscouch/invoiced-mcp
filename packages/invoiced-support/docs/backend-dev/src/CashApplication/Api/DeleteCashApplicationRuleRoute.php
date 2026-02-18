<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationRule;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<CashApplicationRule>
 */
class DeleteCashApplicationRuleRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: CashApplicationRule::class,
            features: ['accounts_receivable'],
        );
    }
}
