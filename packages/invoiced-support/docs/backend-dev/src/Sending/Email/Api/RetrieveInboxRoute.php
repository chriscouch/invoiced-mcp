<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxDecorator;

/**
 * @extends AbstractRetrieveModelApiRoute<InboxDecorator>
 */
class RetrieveInboxRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private string $inboundEmailDomain,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Inbox::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): InboxDecorator
    {
        /** @var Inbox $inbox */
        $inbox = parent::retrieveModel($context);

        return new InboxDecorator($inbox, $this->inboundEmailDomain);
    }
}
