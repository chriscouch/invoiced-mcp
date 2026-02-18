<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Reports\Models\SavedReport;
use App\Reports\Models\ScheduledReport;

class CreateScheduledReportRoute extends AbstractCreateModelApiRoute
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

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;
        $requestParameters['saved_report'] = SavedReport::find($context->requestParameters['saved_report'] ?? null);
        $context = $context->withRequestParameters($requestParameters);

        return parent::buildResponse($context);
    }
}
