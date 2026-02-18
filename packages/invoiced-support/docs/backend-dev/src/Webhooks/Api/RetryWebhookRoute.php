<?php

namespace App\Webhooks\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Queue\Queue;
use App\Webhooks\Models\WebhookAttempt;
use Symfony\Component\HttpFoundation\Response;

class RetryWebhookRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private Queue $queue)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: WebhookAttempt::class,
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        /** @var WebhookAttempt $attempt */
        $attempt = parent::buildResponse($context);

        $attempt->queue($this->queue);

        return new Response('', 204);
    }
}
