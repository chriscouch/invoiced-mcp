<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\Bill;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<Bill>
 */
class ListBillsApiRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Bill::class,
            filterableProperties: ['network_document'],
            features: ['accounts_payable'],
        );
    }
}
