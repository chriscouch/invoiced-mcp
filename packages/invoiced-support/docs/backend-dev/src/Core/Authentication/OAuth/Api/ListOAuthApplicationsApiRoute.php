<?php

namespace App\Core\Authentication\OAuth\Api;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<OAuthApplication>
 */
class ListOAuthApplicationsApiRoute extends AbstractListModelsApiRoute
{
    public function __construct(ApiCache $apiCache, private TenantContext $tenant)
    {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: OAuthApplication::class,
            features: ['api'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        // Apply permissions
        $query->where('tenant_id', $this->tenant->get());

        return $query;
    }
}
