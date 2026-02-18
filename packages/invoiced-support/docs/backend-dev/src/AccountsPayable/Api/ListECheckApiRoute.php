<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\ECheck;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<ECheck>
 */
class ListECheckApiRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: ECheck::class,
            filterableProperties: ['payment_id'],
            features: ['accounts_payable'],
        );
    }
}
