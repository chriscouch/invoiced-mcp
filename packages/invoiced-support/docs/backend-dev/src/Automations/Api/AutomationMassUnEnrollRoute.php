<?php

namespace App\Automations\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\AutomationMassUnEnrollmentQueueJob;
use Symfony\Component\HttpFoundation\Response;

class AutomationMassUnEnrollRoute extends AbstractApiRoute
{
    public function __construct(private readonly Queue $queue)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [
                'options' => new QueryParameter(
                    required: true,
                    types: ['string'],
                ),
            ],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            features: ['automations'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $options = json_decode($context->queryParameters['options']);
        $this->queue->enqueue(AutomationMassUnEnrollmentQueueJob::class, [
            'workflow_id' => $context->request->attributes->get('model_id'),
            'options' => $options,
        ]);

        return $this->getSuccessfulResponse();
    }
}
