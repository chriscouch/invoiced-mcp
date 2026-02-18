<?php

namespace App\Integrations\Xero\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Xero\Models\XeroSyncProfile;

/**
 * @extends AbstractEditModelApiRoute<XeroSyncProfile>
 */
class EditXeroSyncProfileRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: XeroSyncProfile::class,
            features: ['accounting_sync'],
        );
    }
}
