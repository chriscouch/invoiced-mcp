<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Reports\Models\SavedReport;

/**
 * @extends AbstractRetrieveModelApiRoute<SavedReport>
 */
class RetrieveSavedReportRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: SavedReport::class,
        );
    }
}
