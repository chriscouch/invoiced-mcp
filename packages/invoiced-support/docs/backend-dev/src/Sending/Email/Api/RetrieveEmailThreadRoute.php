<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\EmailThread;

/**
 * @extends AbstractRetrieveModelApiRoute<EmailThread>
 */
class RetrieveEmailThreadRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: EmailThread::class,
        );
    }
}
