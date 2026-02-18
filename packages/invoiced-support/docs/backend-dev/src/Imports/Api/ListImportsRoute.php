<?php

namespace App\Imports\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Imports\Models\Import;

/**
 * @extends AbstractListModelsApiRoute<Import>
 */
class ListImportsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Import::class,
            filterableProperties: ['type', 'status', 'num_imported', 'num_updated', 'num_failed', 'name', 'user', 'type', 'total_records', 'created_at'],
        );
    }
}
