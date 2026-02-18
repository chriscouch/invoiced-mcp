<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Reports\Models\ScheduledReport;

/**
 * @extends AbstractListModelsApiRoute<ScheduledReport>
 */
class ListScheduledReportsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['reports.create'],
            modelClass: ScheduledReport::class,
            features: ['report_builder'],
        );
    }
}
