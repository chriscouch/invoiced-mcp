<?php

namespace App\Core\Authentication\OAuth\Api;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<OAuthApplication>
 */
class EditOAuthApplicationApiRoute extends AbstractEditModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(
                    types: ['string'],
                ),
                'redirect_uris' => new RequestParameter(
                    types: ['array'],
                ),
            ],
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

    public function buildResponse(ApiCallContext $context): mixed
    {
        if (isset($context->requestParameters['redirect_uris'])) {
            $redirectUris = $context->requestParameters['redirect_uris'];
            foreach ($redirectUris as $uri) {
                if (!str_starts_with($uri, 'http://') && !str_starts_with($uri, 'https://') && !filter_var($uri, FILTER_VALIDATE_URL)) {
                    throw new InvalidRequest('Invalid redirect URL: '.$uri);
                }
            }
        }

        return parent::buildResponse($context);
    }
}
