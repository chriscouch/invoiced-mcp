<?php

namespace App\AccountsReceivable\Api\Items;

use App\AccountsReceivable\Models\Item;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Item>
 */
class RetrieveItemRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Item::class,
            features: ['accounts_receivable'],
        );
    }
}
