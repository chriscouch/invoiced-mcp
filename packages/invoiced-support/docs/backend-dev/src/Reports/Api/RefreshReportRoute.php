<?php

namespace App\Reports\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Reports\Libs\StartReportJob;
use App\Reports\Models\Report;
use App\Reports\Traits\BuildReportApiTrait;
use Symfony\Component\HttpFoundation\Response;

class RefreshReportRoute extends AbstractRetrieveModelApiRoute
{
    use BuildReportApiTrait;

    private array $parameters = [];

    public function __construct(TenantContext $tenant, StartReportJob $startReportJob)
    {
        $this->startReportJob = $startReportJob;
        $this->tenant = $tenant;
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'parameters' => new RequestParameter(
                    types: ['array'],
                    default: [],
                ),
            ],
            requiredPermissions: ['reports.create'],
            modelClass: Report::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->parameters = $context->requestParameters['parameters'];

        /** @var Report $report */
        $report = parent::buildResponse($context);

        return $this->startReport($report->type, $report->definition, $this->parameters);
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
