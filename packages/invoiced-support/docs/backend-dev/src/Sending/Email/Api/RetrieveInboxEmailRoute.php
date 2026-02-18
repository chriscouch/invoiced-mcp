<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\InboxEmail;

/**
 * @extends AbstractRetrieveModelApiRoute<InboxEmail>
 */
class RetrieveInboxEmailRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: InboxEmail::class,
        );
    }
}
