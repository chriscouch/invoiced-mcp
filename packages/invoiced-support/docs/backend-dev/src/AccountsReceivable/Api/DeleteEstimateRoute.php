<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Estimate;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<Estimate>
 */
class DeleteEstimateRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['estimates.delete'],
            modelClass: Estimate::class,
            features: ['accounts_receivable'],
        );
    }
}
