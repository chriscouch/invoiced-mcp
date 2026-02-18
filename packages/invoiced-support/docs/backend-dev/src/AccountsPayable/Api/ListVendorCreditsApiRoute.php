<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorCredit;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<VendorCredit>
 */
class ListVendorCreditsApiRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorCredit::class,
            filterableProperties: ['network_document'],
            features: ['accounts_payable'],
        );
    }
}
