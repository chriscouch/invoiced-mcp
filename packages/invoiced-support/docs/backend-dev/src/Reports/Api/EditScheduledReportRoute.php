<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Reports\Models\ScheduledReport;

/**
 * @extends AbstractEditModelApiRoute<ScheduledReport>
 */
class EditScheduledReportRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['reports.create'],
            modelClass: ScheduledReport::class,
            features: ['report_builder'],
        );
    }
}
