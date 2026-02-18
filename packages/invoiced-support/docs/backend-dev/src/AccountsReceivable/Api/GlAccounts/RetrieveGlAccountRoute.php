<?php

namespace App\AccountsReceivable\Api\GlAccounts;

use App\AccountsReceivable\Models\GlAccount;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<GlAccount>
 */
class RetrieveGlAccountRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: GlAccount::class,
        );
    }
}
