<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\ContactRole;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<ContactRole>
 */
class ListContactRolesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: ContactRole::class,
            features: ['accounts_receivable'],
        );
    }
}
