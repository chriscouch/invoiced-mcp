<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\CompanyBankAccount;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<CompanyBankAccount>
 */
class RetrieveCompanyBankAccountsApiRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CompanyBankAccount::class,
            features: ['accounts_payable'],
        );
    }
}
