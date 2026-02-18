<?php

namespace App\Core\Authentication\OAuth;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Authentication\OAuth\Models\OAuthApplicationAuthorization;
use App\Core\Authentication\OAuth\ValueObjects\OAuthTenantUser;
use App\Core\Authentication\OAuth\ValueObjects\OAuthUser;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ApproveOAuthApplication
{
    public function __construct(
        private OAuthServerFactory $factory
    ) {
    }

    /**
     * Approves a request to authorize an application
     * for a given user.
     */
    public function approveForUser(User $user, AuthorizationRequest $authRequest): Response
    {
        $authRequest->setAuthorizationApproved(true);
        $authRequest->setUser(new OAuthUser($user));

        /** @var OAuthApplication $application */
        $application = $authRequest->getClient();
        $authorization = OAuthApplicationAuthorization::where('application_id', $application)
            ->where('user_id', $user)
            ->oneOrNull();
        if (!$authorization) {
            $authorization = new OAuthApplicationAuthorization();
            $authorization->application = $application;
            $authorization->user = $user;
        }

        // Record the user's authorization
        $authorization->scopes = $authRequest->getScopes();
        $authorization->saveOrFail();

        return $this->makeResponse($authRequest);
    }

    /**
     * Approves a request to authorize an application
     * for a given user and tenant.
     */
    public function approveForTenant(User $user, Company $tenant, AuthorizationRequest $authRequest): Response
    {
        $authRequest->setAuthorizationApproved(true);
        $authRequest->setUser(new OAuthTenantUser($tenant));
        /** @var OAuthApplication $application */
        $application = $authRequest->getClient();

        $authorization = OAuthApplicationAuthorization::where('application_id', $application)
            ->where('user_id', $user)
            ->where('tenant_id', $tenant)
            ->oneOrNull();
        if (!$authorization) {
            $authorization = new OAuthApplicationAuthorization();
            $authorization->application = $application;
            $authorization->user = $user;
            $authorization->tenant = $tenant;
        }

        // Record the user's authorization
        $authorization->scopes = $authRequest->getScopes();
        $authorization->saveOrFail();

        return $this->makeResponse($authRequest);
    }

    /**
     * Denies a request to authorize an application.
     */
    public function deny(User $user, AuthorizationRequest $authRequest): Response
    {
        $authRequest->setAuthorizationApproved(false);
        // A user is required but is not actually used
        $authRequest->setUser(new OAuthUser($user));

        return $this->makeResponse($authRequest);
    }

    /**
     * Returns the HTTP redirect response for the authorization request.
     */
    private function makeResponse(AuthorizationRequest $authRequest): Response
    {
        try {
            $server = $this->factory->getAuthorizationServer();
            $psrResponse = $server->completeAuthorizationRequest(
                $authRequest,
                $this->factory->convertResponseToPsr(new Response())
            );

            return (new HttpFoundationFactory())->createResponse($psrResponse);
        } catch (OAuthServerException $e) {
            $redirectUri = $authRequest->getRedirectUri();
            $queryParams = [
                'state' => $authRequest->getState(),
                'error_description' => $e->getMessage(),
            ];

            return new RedirectResponse($redirectUri.'?'.http_build_query($queryParams));
        }
    }
}
