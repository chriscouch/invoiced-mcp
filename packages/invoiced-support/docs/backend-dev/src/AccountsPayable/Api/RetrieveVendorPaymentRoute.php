<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPayment;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<VendorPayment>
 */
class RetrieveVendorPaymentRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorPayment::class,
            features: ['accounts_payable'],
        );
    }
}
