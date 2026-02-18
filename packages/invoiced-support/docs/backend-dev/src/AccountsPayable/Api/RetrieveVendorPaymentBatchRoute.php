<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPaymentBatch;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class RetrieveVendorPaymentBatchRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorPaymentBatch::class,
            features: ['accounts_payable'],
        );
    }
}
