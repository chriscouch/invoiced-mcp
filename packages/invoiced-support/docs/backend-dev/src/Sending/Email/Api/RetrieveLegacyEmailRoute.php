<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\Email;

/**
 * @extends AbstractRetrieveModelApiRoute<Email>
 */
class RetrieveLegacyEmailRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Email::class,
        );
    }
}
