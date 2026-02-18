<?php

namespace App\Core\RestApi\Api;

use App\Core\RestApi\Models\ApiKey;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<ApiKey>
 */
class DeleteApiKeyRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['business.admin'],
            modelClass: ApiKey::class,
            features: ['api'],
        );
    }
}
