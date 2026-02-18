<?php

namespace App\Exports\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Exports\Models\Export;

/**
 * @extends AbstractDeleteModelApiRoute<Export>
 */
class DeleteExportRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Export::class,
        );
    }
}
