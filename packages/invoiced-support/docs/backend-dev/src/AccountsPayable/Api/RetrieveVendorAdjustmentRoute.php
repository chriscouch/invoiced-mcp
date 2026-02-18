<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorAdjustment;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<VendorAdjustment>
 */
class RetrieveVendorAdjustmentRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorAdjustment::class,
            features: ['accounts_payable'],
        );
    }
}
