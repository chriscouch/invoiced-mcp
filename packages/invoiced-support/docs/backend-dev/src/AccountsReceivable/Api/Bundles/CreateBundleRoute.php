<?php

namespace App\AccountsReceivable\Api\Bundles;

use App\AccountsReceivable\Models\Bundle;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractCreateModelApiRoute<Bundle>
 */
class CreateBundleRoute extends AbstractCreateModelApiRoute
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
