<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\CspTrustedSite;

/**
 * @extends AbstractDeleteModelApiRoute<CspTrustedSite>
 */
class DeleteCspTrustedSiteRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: CspTrustedSite::class,
            features: ['billing_portal'],
        );
    }
}
