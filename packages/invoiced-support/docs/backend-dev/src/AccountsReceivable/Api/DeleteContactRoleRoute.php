<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\ContactRole;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<ContactRole>
 */
class DeleteContactRoleRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: ContactRole::class,
            features: ['accounts_receivable'],
        );
    }
}
