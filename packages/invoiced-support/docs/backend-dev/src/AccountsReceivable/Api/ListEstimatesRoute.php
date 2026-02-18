<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Estimate;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class ListEstimatesRoute extends ListDocumentsRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Estimate::class,
            filterableProperties: ['network_document'],
            features: ['accounts_receivable'],
        );
    }
}
