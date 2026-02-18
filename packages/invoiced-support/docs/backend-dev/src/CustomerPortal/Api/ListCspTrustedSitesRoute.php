<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\CustomerPortal\Models\CspTrustedSite;

/**
 * @extends AbstractListModelsApiRoute<CspTrustedSite>
 */
class ListCspTrustedSitesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CspTrustedSite::class,
            features: ['billing_portal'],
        );
    }
}
