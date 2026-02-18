<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxDecorator;

/**
 * @extends AbstractListModelsApiRoute<Inbox>
 */
class ListInboxesRoute extends AbstractListModelsApiRoute
{
    public function __construct(
        private string $inboundEmailDomain,
        ApiCache $apiCache
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Inbox::class,
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $data = parent::buildResponse($context);

        $decoratedResult = [];
        foreach ($data as $item) {
            // inbox decorator should not be passed as DI, since we need to follow the Model behavior
            $decoratedResult[] = new InboxDecorator($item, $this->inboundEmailDomain);
        }

        return $decoratedResult;
    }
}
