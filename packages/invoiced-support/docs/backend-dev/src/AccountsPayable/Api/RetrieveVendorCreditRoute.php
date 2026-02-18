<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorCredit;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<VendorCredit>
 */
class RetrieveVendorCreditRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorCredit::class,
            features: ['accounts_payable'],
        );
    }
}
