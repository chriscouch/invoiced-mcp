<?php

namespace App\Sending\Mail\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Mail\Models\Letter;

/**
 * @extends AbstractRetrieveModelApiRoute<Letter>
 */
class RetrieveLetterRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Letter::class,
        );
    }
}
