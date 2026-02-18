<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\CspTrustedSite;

/**
 * @extends AbstractEditModelApiRoute<CspTrustedSite>
 */
class EditCspTrustedSiteRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: CspTrustedSite::class,
            features: ['billing_portal'],
        );
    }
}
