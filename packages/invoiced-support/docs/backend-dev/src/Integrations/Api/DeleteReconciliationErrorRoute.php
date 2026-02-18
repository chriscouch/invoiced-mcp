<?php

namespace App\Integrations\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Models\ReconciliationError;

/**
 * @extends AbstractDeleteModelApiRoute<ReconciliationError>
 */
class DeleteReconciliationErrorRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: [],
            modelClass: ReconciliationError::class,
        );
    }
}
