<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<CashApplicationBankAccount>
 */
class ListCashApplicationBankAccountsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CashApplicationBankAccount::class,
        );
    }
}
