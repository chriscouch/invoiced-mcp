<?php

namespace App\Integrations\OAuth;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Model;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\Interfaces\OAuthIntegrationInterface;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class OAuthConnectionManager
{
    protected bool $noRefresh = false;

    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private RequestStack $requestStack,
        private TenantContext $tenant,
        private UserContext $userContext,
        private string $dashboardUrl,
        private LockFactory $lockFactory,
    ) {
    }

    public function start(OAuthIntegrationInterface $oauth): Response
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $cId = $request->query->get('company');
        $company = Company::find($cId);
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $this->checkEditPermission($company);

        $request->getSession()->set('oauthCompany', $cId);

        // generate a CSRF token as a random state value
        $state = $this->csrfTokenManager->getToken('oauthState')->getValue();

        return new RedirectResponse($oauth->getAuthorizationUrl($state), 302, [
            // do not cache this page
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }

    /**
     * @throws OAuthException
     */
    public function handleAccessToken(OAuthIntegrationInterface $oauth): Response
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // verify the state sent back matches our session
        $csrfToken = new CsrfToken('oauthState', (string) $request->query->get('state'));
        if (!$this->csrfTokenManager->isTokenValid($csrfToken)) {
            throw new UnauthorizedHttpException('');
        }

        $session = $request->getSession();
        $company = Company::find($session->get('oauthCompany'));
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $this->checkEditPermission($company);

        $code = (string) $request->query->get('code');
        if (!$code) {
            $errorMsg = $request->query->get('error_description');
            if (!$errorMsg) {
                $errorMsg = $request->query->get('error');
            }

            throw new OAuthException('Connection failed. '.$errorMsg);
        }

        $this->noRefresh = true;
        $accessToken = $oauth->exchangeAuthCodeForToken($code);

        $account = $oauth->getAccount() ?? $oauth->makeAccount();
        $oauth->handleAccessToken($accessToken, $account, $request);
        $account->persistOAuth();

        $session->set('oauthCompany', null);
        $this->noRefresh = false;

        return new RedirectResponse($oauth->getSuccessRedirectUrl(), 302, [
            // do not cache this page
            'Cache-Control' => 'no-cache, no-store',
        ]);
    }

    /**
     * @throws OAuthException
     */
    public function refresh(OAuthIntegrationInterface $oauth, OAuthAccountInterface $account): void
    {
        // prevent infinite loops if handleAccessToken makes an API call
        if ($this->noRefresh) {
            return;
        }

        // only refresh if the token is expired
        // or expiring within the next 5 minutes
        $accessToken = $account->getToken();
        if ($accessToken->accessTokenExpiration->greaterThan(new CarbonImmutable('+5 minutes'))) {
            return;
        }

        // obtain a lock so that no other processes
        // can refresh the token
        $lock = $this->getLock($account);
        if ($lock->acquire()) {
            $this->noRefresh = true;
            $oauth->refresh($account);
            $account->persistOAuth();
            $this->noRefresh = false;
        }
    }

    public function disconnect(OAuthIntegrationInterface $oauth): Response
    {
        $account = $oauth->getAccount();
        if ($account) {
            try {
                $oauth->disconnect($account);
            } catch (OAuthException) {
                // ignore failures on disconnect
            }

            $account->deleteOAuth();
        }

        return new RedirectResponse($this->dashboardUrl.'/settings/apps');
    }

    private function checkEditPermission(Company $company): void
    {
        $user = $this->userContext->get();
        if (!$user) {
            throw new NotFoundHttpException();
        }

        $member = Member::getForUser($user);
        if (!$member || !$company->memberCanEdit($member)) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * Builds a refresh token generation lock.
     */
    private function getLock(OAuthAccountInterface $account): LockInterface
    {
        $id = $account instanceof Model ? $account->id() : $account->getToken()->accessToken;

        return $this->lockFactory->createLock('oauth_lock:'.$id, 60);
    }
}
