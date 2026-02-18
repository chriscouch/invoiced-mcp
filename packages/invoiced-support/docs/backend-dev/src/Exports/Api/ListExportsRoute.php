<?php

namespace App\Exports\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Exports\Models\Export;

/**
 * @extends AbstractListModelsApiRoute<Export>
 */
class ListExportsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Export::class,
            filterableProperties: ['type', 'status'],
        );
    }
}
