<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Reports\Models\SavedReport;

/**
 * @extends AbstractCreateModelApiRoute<SavedReport>
 */
class CreateSavedReportRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(),
                'definition' => new RequestParameter(),
                'private' => new RequestParameter(),
            ],
            requiredPermissions: ['reports.create'],
            modelClass: SavedReport::class,
        );
    }
}
