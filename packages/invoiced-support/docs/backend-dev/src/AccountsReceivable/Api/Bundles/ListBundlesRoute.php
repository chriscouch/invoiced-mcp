<?php

namespace App\AccountsReceivable\Api\Bundles;

use App\AccountsReceivable\Models\Bundle;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<Bundle>
 */
class ListBundlesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Bundle::class,
            features: ['accounts_receivable'],
        );
    }
}
