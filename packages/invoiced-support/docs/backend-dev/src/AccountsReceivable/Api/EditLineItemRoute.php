<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\LineItem;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractEditModelApiRoute<LineItem>
 */
class EditLineItemRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: [],
            modelClass: LineItem::class,
            features: ['accounts_receivable'],
        );
    }
}
