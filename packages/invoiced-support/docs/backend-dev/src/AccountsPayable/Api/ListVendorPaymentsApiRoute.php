<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPayment;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<VendorPayment>
 */
class ListVendorPaymentsApiRoute extends AbstractListModelsApiRoute
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
