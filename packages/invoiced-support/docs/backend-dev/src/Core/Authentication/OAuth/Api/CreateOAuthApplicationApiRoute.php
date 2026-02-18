<?php

namespace App\Core\Authentication\OAuth\Api;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Exception\ModelException;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractCreateModelApiRoute<OAuthApplication>
 */
class CreateOAuthApplicationApiRoute extends AbstractCreateModelApiRoute
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
                    required: true,
                    types: ['string'],
                ),
                'redirect_uris' => new RequestParameter(
                    required: true,
                    types: ['array'],
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: OAuthApplication::class,
            features: ['api'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $name = $context->requestParameters['name'];

        $redirectUris = $context->requestParameters['redirect_uris'];
        $redirectUris = array_unique($redirectUris);
        foreach ($redirectUris as $uri) {
            if (!str_starts_with($uri, 'http://') && !str_starts_with($uri, 'https://') && !filter_var($uri, FILTER_VALIDATE_URL)) {
                throw new InvalidRequest('Invalid redirect URL: '.$uri);
            }
        }

        try {
            return OAuthApplication::makeNewApp($name, $redirectUris, $this->tenant->get()->id);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
