<?php

namespace App\AccountsReceivable\Api\GlAccounts;

use App\AccountsReceivable\Models\GlAccount;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<GlAccount>
 */
class EditGlAccountRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(),
                'code' => new RequestParameter(),
                'parent_id' => new RequestParameter(),
            ],
            requiredPermissions: ['catalog.edit'],
            modelClass: GlAccount::class,
        );
    }
}
