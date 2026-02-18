<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\Vendor;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Vendor>
 */
class RetrieveVendorRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Vendor::class,
            features: ['accounts_payable'],
        );
    }
}
