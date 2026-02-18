<?php

namespace App\AccountsReceivable\Api\Bundles;

use App\AccountsReceivable\Models\Bundle;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<Bundle>
 */
class DeleteBundleRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['catalog.edit'],
            modelClass: Bundle::class,
            features: ['accounts_receivable'],
        );
    }
}
