<?php

namespace App\Core\RestApi\Api;

use App\Core\RestApi\Models\ApiKey;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractCreateModelApiRoute<ApiKey>
 */
class CreateApiKeyRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['business.admin'],
            modelClass: ApiKey::class,
            features: ['api'],
        );
    }
}
