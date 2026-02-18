<?php

namespace App\Chasing\Api;

use App\Chasing\LateFees\LateFeeAssessor;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;

class RunLateFeeScheduleRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private LateFeeAssessor $lateFeeAssessor)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: LateFeeSchedule::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var LateFeeSchedule $schedule */
        $schedule = parent::buildResponse($context);
        $this->lateFeeAssessor->queue($schedule);

        return new Response('', 204);
    }
}
