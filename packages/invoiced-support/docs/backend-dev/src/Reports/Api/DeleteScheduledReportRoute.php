<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Reports\Models\ScheduledReport;

/**
 * @extends AbstractDeleteModelApiRoute<ScheduledReport>
 */
class DeleteScheduledReportRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['reports.create'],
            modelClass: ScheduledReport::class,
            features: ['report_builder'],
        );
    }
}
