<?php

namespace App\Core\RestApi\Api;

use App\Core\RestApi\Models\ApiKey;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<ApiKey>
 */
class ListApiKeysRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['business.admin'],
            modelClass: ApiKey::class,
            features: ['api'],
        );
    }
}
