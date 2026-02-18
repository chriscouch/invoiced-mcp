<?php

namespace App\AccountsReceivable\Api\Bundles;

use App\AccountsReceivable\Models\Bundle;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<Bundle>
 */
class RetrieveBundleRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Bundle::class,
            features: ['accounts_receivable'],
        );
    }
}
