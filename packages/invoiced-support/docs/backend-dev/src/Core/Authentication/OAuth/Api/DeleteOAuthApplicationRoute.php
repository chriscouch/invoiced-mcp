<?php

namespace App\Core\Authentication\OAuth\Api;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<OAuthApplication>
 */
class DeleteOAuthApplicationRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: OAuthApplication::class,
            features: ['api'],
        );
    }

    public function retrieveModel(ApiCallContext $context)
    {
        $application = parent::retrieveModel($context);
        // Validate permissions
        if ($application->tenant_id != $this->tenant->get()->id) {
            throw $this->modelNotFoundError();
        }

        return $application;
    }
}
