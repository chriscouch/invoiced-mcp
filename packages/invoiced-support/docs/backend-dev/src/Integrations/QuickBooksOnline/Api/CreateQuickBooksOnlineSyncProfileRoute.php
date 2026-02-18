<?php

namespace App\Integrations\QuickBooksOnline\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;

/**
 * @extends AbstractCreateModelApiRoute<QuickBooksOnlineSyncProfile>
 */
class CreateQuickBooksOnlineSyncProfileRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: QuickBooksOnlineSyncProfile::class,
            features: ['accounting_sync'],
        );
    }
}
