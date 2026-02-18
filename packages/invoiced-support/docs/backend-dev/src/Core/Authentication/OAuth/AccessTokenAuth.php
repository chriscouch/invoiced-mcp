<?php

namespace App\Core\Authentication\OAuth;

use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Core\RestApi\Exception\ApiHttpException;
use League\OAuth2\Server\Exception\OAuthServerException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

class AccessTokenAuth
{
    public function __construct(
        private UserContext $userContext,
        private TenantContext $tenant,
        private OAuthServerFactory $oauthFactory,
    ) {
    }

    /**
     * Checks if there is an OAuth access token in the request.
     */
    public function isOAuthRequest(Request $request): bool
    {
        // look for header "Authorization: Bearer <token>"
        return $request->headers->has('Authorization')
            && str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    /**
     * Authenticates a request with an OAuth access token.
     *
     * @throws ApiHttpException when the request cannot be authenticated
     */
    public function handleRequest(Request $request): void
    {
        $server = $this->oauthFactory->getResourceServer();
        $psrRequest = $this->oauthFactory->convertRequestToPsr($request);

        try {
            $authenticatedRequest = $server->validateAuthenticatedRequest($psrRequest);
        } catch (OAuthServerException $e) {
            throw new ApiHttpException($e->getHttpStatusCode(), $e->getMessage(), $e);
        }

        // Add attributes from authenticated request
        // e.g. oauth_access_token_id, oauth_client_id, oauth_user_id, oauth_scopes
        foreach ($authenticatedRequest->getAttributes() as $k => $v) {
            $request->attributes->set($k, $v);
        }

        // Load the authenticated user
        $this->loadAuthenticatedUser($request);
    }

    private function loadAuthenticatedUser(Request $request): void
    {
        $oauthUserId = $request->attributes->get('oauth_user_id');

        if (str_starts_with($oauthUserId, 'user:')) {
            $userId = substr($oauthUserId, 5);
            $user = User::findOrFail($userId);

            // Set the user as the requester
            $this->userContext->set($user);
            ACLModelRequester::set($user);

            return;
        }

        if (str_starts_with($oauthUserId, 'tenant:')) {
            $tenantId = substr($oauthUserId, 7);
            $company = Company::findOrFail($tenantId);

            // Set the company as the requester
            $this->tenant->set($company);
            ACLModelRequester::set($company);

            // Set the current user to the API user
            $this->userContext->set(new User(['id' => User::API_USER]));

            // Mark the request as already authenticated
            $request->attributes->set('skip_api_authentication', true);

            return;
        }

        throw new RuntimeException('OAuth user type not recognized: '.$oauthUserId);
    }

    public function setFactory(OAuthServerFactory $factory): void
    {
        $this->oauthFactory = $factory;
    }
}
