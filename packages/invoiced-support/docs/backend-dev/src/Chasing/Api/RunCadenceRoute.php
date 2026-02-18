<?php

namespace App\Chasing\Api;

use App\Chasing\CustomerChasing\CustomerChasingRun;
use App\Chasing\Models\ChasingCadence;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;

class RunCadenceRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private CustomerChasingRun $chasingRun)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: ChasingCadence::class,
            features: ['smart_chasing'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $cadence = parent::buildResponse($context);
        $this->chasingRun->queue($cadence);

        return new Response('', 204);
    }
}
