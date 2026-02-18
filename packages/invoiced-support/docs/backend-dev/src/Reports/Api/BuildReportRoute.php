<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Reports\Libs\StartReportJob;
use App\Reports\Models\Report;
use App\Reports\Traits\BuildReportApiTrait;
use Symfony\Component\HttpFoundation\Response;

class BuildReportRoute extends AbstractApiRoute
{
    use BuildReportApiTrait;

    public function __construct(TenantContext $tenant, StartReportJob $startReportJob)
    {
        $this->startReportJob = $startReportJob;
        $this->tenant = $tenant;
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['reports.create'],
        );
    }

    public function buildResponse(ApiCallContext $context): Report
    {
        $type = (string) $context->request->request->get('type', 'custom');
        $definition = $context->request->request->all('definition') ?: null;
        $parameters = $context->request->request->all('parameters');

        return $this->startReport($type, $definition, $parameters);
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
