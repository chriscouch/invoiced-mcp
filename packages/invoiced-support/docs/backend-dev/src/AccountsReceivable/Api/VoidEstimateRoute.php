<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Estimate;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class VoidEstimateRoute extends VoidDocumentRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['estimates.void'],
            modelClass: Estimate::class,
            features: ['accounts_receivable'],
        );
    }
}
