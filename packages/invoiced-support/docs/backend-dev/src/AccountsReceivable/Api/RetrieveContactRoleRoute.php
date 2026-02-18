<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\ContactRole;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<ContactRole>
 */
class RetrieveContactRoleRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: ContactRole::class,
            features: ['accounts_receivable'],
        );
    }
}
