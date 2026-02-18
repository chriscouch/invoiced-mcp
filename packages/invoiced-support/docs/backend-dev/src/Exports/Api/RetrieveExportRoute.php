<?php

namespace App\Exports\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Exports\Models\Export;

/**
 * @extends AbstractRetrieveModelApiRoute<Export>
 */
class RetrieveExportRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Export::class,
        );
    }
}
