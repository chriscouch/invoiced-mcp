<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\LineItem;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<LineItem>
 */
class DeleteLineItemRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: [],
            modelClass: LineItem::class,
            features: ['accounts_receivable'],
        );
    }
}
