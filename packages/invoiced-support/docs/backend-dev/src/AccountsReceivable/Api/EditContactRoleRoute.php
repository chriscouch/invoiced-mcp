<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\ContactRole;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractEditModelApiRoute<ContactRole>
 */
class EditContactRoleRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: ContactRole::class,
            features: ['accounts_receivable'],
        );
    }
}
