<?php

namespace App\AccountsReceivable\Api\Items;

use App\AccountsReceivable\Models\Item;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<Item>
 */
class DeleteItemRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['catalog.edit'],
            modelClass: Item::class,
            features: ['accounts_receivable'],
        );
    }
}
