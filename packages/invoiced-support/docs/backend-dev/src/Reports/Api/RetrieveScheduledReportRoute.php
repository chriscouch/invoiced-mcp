<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Reports\Models\ScheduledReport;

/**
 * @extends AbstractRetrieveModelApiRoute<ScheduledReport>
 */
class RetrieveScheduledReportRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: ScheduledReport::class,
            features: ['report_builder'],
        );
    }
}
