<?php

namespace App\AccountsReceivable\Api\Bundles;

use App\AccountsReceivable\Models\Bundle;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractEditModelApiRoute<Bundle>
 */
class EditBundleRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['catalog.edit'],
            modelClass: Bundle::class,
            features: ['accounts_receivable'],
        );
    }
}
