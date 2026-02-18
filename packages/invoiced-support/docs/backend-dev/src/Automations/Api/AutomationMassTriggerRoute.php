<?php

namespace App\Automations\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\AutomationMassManualTriggerQueueJob;
use Symfony\Component\HttpFoundation\Response;

class AutomationMassTriggerRoute extends AbstractRetrieveModelApiRoute
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
        $this->queue->enqueue(AutomationMassManualTriggerQueueJob::class, [
            'workflow_id' => $context->request->attributes->get('model_id'),
            'options' => $options ?? [],
        ]);

        return $this->getSuccessfulResponse();
    }
}
