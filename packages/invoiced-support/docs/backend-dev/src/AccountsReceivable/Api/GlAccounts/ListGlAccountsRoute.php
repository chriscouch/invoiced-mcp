<?php

namespace App\AccountsReceivable\Api\GlAccounts;

use App\AccountsReceivable\Models\GlAccount;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<GlAccount>
 */
class ListGlAccountsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: GlAccount::class,
            features: [],
        );
    }
}
