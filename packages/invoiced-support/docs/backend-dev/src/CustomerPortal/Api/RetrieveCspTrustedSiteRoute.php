<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\CspTrustedSite;

/**
 * @extends AbstractRetrieveModelApiRoute<CspTrustedSite>
 */
class RetrieveCspTrustedSiteRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: CspTrustedSite::class,
            features: ['billing_portal'],
        );
    }
}
