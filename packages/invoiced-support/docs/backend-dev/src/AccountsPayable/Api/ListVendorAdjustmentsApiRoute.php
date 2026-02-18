<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorAdjustment;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<VendorAdjustment>
 */
class ListVendorAdjustmentsApiRoute extends AbstractListModelsApiRoute
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
