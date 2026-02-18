<?php

namespace App\Imports\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Imports\Models\Import;

/**
 * @extends AbstractRetrieveModelApiRoute<Import>
 */
class RetrieveImportRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Import::class,
        );
    }
}
