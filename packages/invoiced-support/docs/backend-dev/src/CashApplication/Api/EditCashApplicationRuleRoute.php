<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationRule;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractEditModelApiRoute<CashApplicationRule>
 */
class EditCashApplicationRuleRoute extends AbstractEditModelApiRoute
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
