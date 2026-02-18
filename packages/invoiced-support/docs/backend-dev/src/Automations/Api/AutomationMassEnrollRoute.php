<?php

namespace App\Automations\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\AutomationMassEnrollmentQueueJob;
use Symfony\Component\HttpFoundation\Response;

class AutomationMassEnrollRoute extends AbstractApiRoute
{
    public function __construct(private readonly Queue $queue)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'options' => new RequestParameter(
                    required: true,
                    types: ['array'],
                ), ],
            requiredPermissions: ['settings.edit'],
            features: ['automations'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $options = $context->requestParameters['options'];
        $this->queue->enqueue(AutomationMassEnrollmentQueueJob::class, [
            'workflow_id' => $context->request->attributes->get('model_id'),
            'options' => $options ?? [],
        ]);

        return $this->getSuccessfulResponse();
    }
}
