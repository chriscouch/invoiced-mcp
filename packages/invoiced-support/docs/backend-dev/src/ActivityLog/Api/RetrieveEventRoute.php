<?php

namespace App\ActivityLog\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Models\Event;

class RetrieveEventRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private EventStorageInterface $eventStorage)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Event::class,
        );
    }

    public function buildResponse(ApiCallContext $context): Event
    {
        /** @var Event $event */
        $event = parent::buildResponse($context);

        return $event->hydrateFromStorage($this->eventStorage);
    }
}
