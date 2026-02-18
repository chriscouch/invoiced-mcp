<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<CashApplicationBankAccount>
 */
class RetrieveCashApplicationBankAccountRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: CashApplicationBankAccount::class,
        );
    }
}
