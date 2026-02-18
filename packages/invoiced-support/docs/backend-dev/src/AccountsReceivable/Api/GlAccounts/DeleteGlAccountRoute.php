<?php

namespace App\AccountsReceivable\Api\GlAccounts;

use App\AccountsReceivable\Models\GlAccount;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<GlAccount>
 */
class DeleteGlAccountRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['catalog.edit'],
            modelClass: GlAccount::class,
        );
    }
}
