<?php

namespace App\EntryPoint\Controller;

use App\Companies\Exception\NewCompanySignUpException;
use App\Companies\Libs\NewCompanySignUp;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Models\MarketingAttribution;
use App\Companies\Models\Member;
use App\Companies\Verification\EmailVerification;
use App\Core\Authentication\Event\ChangedPasswordEvent;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\EditProtectedUserFields;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Libs\ResetPassword;
use App\Core\Authentication\Libs\SAMLAvailableCompanies;
use App\Core\Authentication\Libs\TwoFactorHelper;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Libs\UserRegistration;
use App\Core\Authentication\LoginStrategy\AbstractOpenIdLoginStrategy;
use App\Core\Authentication\LoginStrategy\GoogleLoginStrategy;
use App\Core\Authentication\LoginStrategy\IntuitLoginStrategy;
use App\Core\Authentication\LoginStrategy\MicrosoftLoginStrategy;
use App\Core\Authentication\LoginStrategy\SamlV1LoginStrategy;
use App\Core\Authentication\LoginStrategy\SamlV2LoginStrategy;
use App\Core\Authentication\LoginStrategy\UsernamePasswordLoginStrategy;
use App\Core\Authentication\LoginStrategy\XeroLoginStrategy;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\ActiveSession;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\OAuth\ApproveOAuthApplication;
use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Authentication\OAuth\OAuthServerFactory;
use App\Core\Authentication\OAuth\Repository\AccessTokenRepository;
use App\Core\Authentication\Saml\SamlAuthFactory;
use App\Core\Authentication\Saml\SamlResponseSimplifiedFactory;
use App\Core\Authentication\TwoFactor\AuthyVerification;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Models\ApiKey;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\AppUrl;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use OneLogin\Saml2\Error;
use OneLogin\Saml2\ValidationError;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(schemes: '%app.protocol%', host: '%app.domain%')]
class AuthController extends AbstractController
{
    #[Route(path: '/auth/register', name: 'dashboard_register_user', methods: ['POST'])]
    public function register(Request $request, UserRegistration $userRegistration, Connection $database, UsernamePasswordLoginStrategy $strategy): JsonResponse
    {
        $params = [
            'first_name' => $request->request->get('first_name'),
            'last_name' => $request->request->get('last_name'),
            'email' => $request->request->get('email'),
            'password' => $request->request->all('password'),
            'ip' => $request->getClientIp(),
        ];

        try {
            $user = $userRegistration->registerUser($params, true, true);
        } catch (AuthException $e) {
            $database->setRollbackOnly();

            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
                'param' => null,
            ], 400);
        }

        // sign user in
        $email = (string) $request->request->get('email');
        $password = $params['password'][0];
        $strategy->login($request, $email, $password, true);

        return new JsonResponse($this->expandUser($user));
    }

    #[Route(path: '/auth/new_company', name: 'dashboard_new_company', methods: ['POST'])]
    public function newCompany(Request $request, TenantContext $tenant, UserContext $userContext, NewCompanySignUp $newCompanySignUp, string $environment): JsonResponse
    {
        try {
            $currentCompany = null;
            $billingProfile = null;
            if ($request->request->has('tenant_id')) {
                $currentCompany = Company::findOrFail($request->request->get('tenant_id'));
                $tenant->set($currentCompany);

                $this->checkEditPermission($currentCompany, $userContext);

                // There is a limit on how many new companies an
                // unpaid (non-test mode) company can create on
                // their billing profile.
                $billingProfile = BillingProfile::getOrCreate($currentCompany);
                if (!$billingProfile->billing_system && !$currentCompany->test_mode) {
                    $count = Company::where('canceled', false)
                        ->where('billing_profile_id', $billingProfile)
                        ->count();
                    $quota = $currentCompany->quota->get(QuotaType::NewCompanyLimit);
                    if ($count >= $quota) {
                        throw new NewCompanySignUpException('You cannot create more than '.$quota.' new companies.');
                    }
                }
            }

            $requestParameters = [
                'country' => $request->request->get('country'),
                'email' => $request->request->get('email'),
            ];
            $parameters = $this->getNewCompanyParams($currentCompany, $billingProfile, $userContext, $requestParameters, $environment);
            $utm = $this->getNewCompanyUtm($currentCompany, $userContext);
            $changeset = $newCompanySignUp->getEntitlements(false, false);

            $tenant->clear();
            $company = $newCompanySignUp->create($parameters, $changeset, $utm);
            $tenant->set($company);

            // we update session on new company being created
            $session = $request->getSession();
            $restrictions = $session->get('company_restrictions');
            if (null !== $restrictions) {
                $restrictions[] = $company->id();
                $session->set('company_restrictions', $restrictions);
            }

            return new JsonResponse(['id' => $company->id]);
        } catch (NewCompanySignUpException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function getNewCompanyParams(?Company $currentCompany, ?BillingProfile $billingProfile, UserContext $userContext, array $requestParameters, string $environment): array
    {
        $isSandbox = 'sandbox' == $environment;

        return array_merge(
            [
                'name' => '',
                'creator_id' => $userContext->getOrFail()->id(),
                'billing_profile' => $billingProfile,
                'time_zone' => $currentCompany?->time_zone ?: 'UTC',
                'test_mode' => $isSandbox,
                'trial_ends' => !$isSandbox ? CarbonImmutable::now()->addDays(30)->getTimestamp() : null,
                'trial_started' => !$isSandbox ? CarbonImmutable::now()->getTimestamp() : null,
            ],
            $requestParameters
        );
    }

    private function getNewCompanyUtm(?Company $currentCompany, UserContext $userContext): array
    {
        // When signing up from existing company then copy its attribution
        if ($currentCompany) {
            $attribution = MarketingAttribution::where('tenant_id', $currentCompany)->oneOrNull();

            return $attribution?->toArray() ?? [];
        }

        $user = $userContext->getOrFail();

        // Attribute companies created by users that sign in with OpenID Connect.
        // NOTE: Google OpenID Connect is not considered here because
        // it is commonly used and not often sourced from their app store.
        if ($user->intuit_claimed_id) {
            return [
                'utm_source' => 'QuickBooks',
                'utm_medium' => 'quickbooks_online_app',
                'utm_campaign' => 'partner',
                'utm_content' => 'OpenIDConnect',
                'utm_term' => 'FreeTrial',
            ];
        } elseif ($user->microsoft_claimed_id) {
            return [
                'utm_source' => 'Microsoft',
                'utm_medium' => 'AppSource',
                'utm_campaign' => 'partner',
                'utm_content' => 'OpenIDConnect',
                'utm_term' => 'FreeTrial',
            ];
        } elseif ($user->xero_claimed_id) {
            return [
                'utm_source' => 'Xero',
                'utm_medium' => 'XeroAppStore',
                'utm_campaign' => 'partner',
                'utm_content' => 'OpenIDConnect',
                'utm_term' => 'FreeTrial',
            ];
        }

        return [];
    }

    #[Route(path: '/login', name: 'login_redirect', methods: ['GET'])]
    public function loginRedirect(Request $request, string $dashboardUrl): RedirectResponse
    {
        if ($invitationId = $request->query->get('invitation')) {
            $request->getSession()->set('redirect_after_login', $this->generateUrl('network_accept_invitation', ['id' => $invitationId], UrlGeneratorInterface::ABSOLUTE_URL));
        }

        return new RedirectResponse($dashboardUrl);
    }

    #[Route(path: '/auth/login', name: 'dashboard_login', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function login(Request $request, UserContext $userContext, UsernamePasswordLoginStrategy $strategy): Response
    {
        try {
            $strategy->authenticate($request);
        } catch (AuthException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
                'attempts_remaining' => $strategy->getRemainingAttempts(),
            ], 400);
        }

        // mark if the user wants remember me
        // this is useful for signing in the user after 2fa verification
        $session = $request->getSession();
        if ($request->request->get('remember')) {
            $session->set('remember_me', true);
        }

        return $this->currentUser($userContext, $session);
    }

    #[Route(path: '/auth/saml/{domain}/metadata', name: 'sso_metadata', methods: ['GET'])]
    public function ssoMetadata(SamlAuthFactory $authFactory, string $domain): Response
    {
        $settings = $authFactory->getMetadataSettings($domain);
        $metadata = $settings->getSPMetadata();
        if ($errors = $settings->validateMetadata($metadata)) {
            return new Response(implode(', ', $errors));
        }

        return new Response($metadata, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

    /**
     * @todo remove POST method after FE change
     */
    #[Route(path: '/auth/sso/login', name: 'sso_login', methods: ['POST', 'GET'], defaults: ['no_database_transaction' => true])]
    public function sso(Request $request, string $dashboardUrl, SamlAuthFactory $authFactory, Mailer $mailer, SAMLAvailableCompanies $SAMLAvailableCompanies): RedirectResponse
    {
        $email = (string) $request->request->get('email', $request->query->get('email'));
        if (!$email) {
            return $this->redirectToSsoLogin($dashboardUrl, '', 'For security reasons we must perform additional verification on your account.', true);
        }

        $user = User::where('email', $email)
            ->oneOrNull();
        if (!$user) {
            return $this->redirectToSsoLogin($dashboardUrl, $email, 'The requested user can not be found.');
        }
        $availableCompanies = $SAMLAvailableCompanies->get($user, $request->query->getInt('company_id'));

        if (!$availableCompanies) {
            return $this->redirectToSsoLogin($dashboardUrl, $email, 'The requested user can not be found.');
        }

        if (count($availableCompanies) > 1) {
            $unique = array_unique(array_map(fn (CompanySamlSettings $a) => $a->cert, $availableCompanies));
            if (count($unique) > 1) {
                $companies = array_map(fn (Company $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ],
                    Company::where('id', array_map(fn (CompanySamlSettings $a) => $a->company_id, $availableCompanies))
                        ->execute()
                );
                $mailer->sendToUser($user, [
                    'subject' => 'Please select the company to log in',
                ], 'sso-select-company', [
                    'companies' => $companies,
                    'url' => AppUrl::get()->build(),
                ]);

                return $this->redirectToSsoLogin($dashboardUrl, $email, 'You have multiply companies we have sent you email to select the company you for log in.', true);
            }
        }

        try {
            $authFactory->get($availableCompanies[0])->login();
        } catch (AuthException|Error $e) {
            return $this->redirectToSsoLogin($dashboardUrl, $email, $e->getMessage());
        }

        return $this->redirectToSsoLogin($dashboardUrl, $email);
    }

    #[Route(path: '/auth/saml/{domain}/login', name: 'sso_start', methods: ['GET'])]
    public function ssoStart(SamlV1LoginStrategy $strategy, Request $request, string $dashboardUrl): RedirectResponse
    {
        try {
            $strategy->authenticate($request);
        } catch (AuthException|Error $e) {
            return $this->redirectToSsoLogin($dashboardUrl, '', $e->getMessage());
        }

        return $this->redirectToSsoLogin($dashboardUrl);
    }

    #[Route(path: '/auth/sso/sp/acs', name: 'sso_acs', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function ssoAcs(Request $request, SamlV1LoginStrategy $strategy, SamlV2LoginStrategy $strategyV2, string $dashboardUrl, SamlResponseSimplifiedFactory $samlResponseSimplified): RedirectResponse
    {
        try {
            $response = $samlResponseSimplified->getInstance();
            $email = $response->getEmail();
        } catch (ValidationError) {
            return $this->redirectToSsoLogin($dashboardUrl, '', 'Unable to validate response from your IdP request.');
        }

        if (!$email) {
            return $this->redirectToSsoLogin($dashboardUrl, '', 'We could not get email from you IdP request. Please check that you set up SSO properly: https://docs.invoiced.com/integrations/saml');
        }

        $user = User::where('email', $email)
            ->oneOrNull();
        if (!$user) {
            return $this->redirectToSsoLogin($dashboardUrl, $email, 'The requested user can not be found.');
        }

        // try v2
        try {
            $strategyV2->doSignIn($request, $user, $response);
        } catch (AuthException $e) {
            // try v1
            try {
                $strategy->doSignIn($request, $user, $response);
            } catch (AuthException) {
                return $this->redirectToSsoLogin($dashboardUrl, '', $e->getMessage());
            }
        }

        $session = $request->getSession();
        if ($session->has('redirect_after_login')) {
            return new RedirectResponse($session->get('redirect_after_login'));
        }

        return new RedirectResponse($dashboardUrl);
    }

    #[Route(path: '/auth/saml/{domain}/logout', name: 'sso_logout', methods: ['GET'])]
    public function ssologout(): Response
    {
        // Not implemented
        return new Response('', 204);
    }

    private function redirectToSsoLogin(string $dashboardUrl, string $email = '', string $message = '', bool $isWarning = false): RedirectResponse
    {
        return new RedirectResponse($dashboardUrl.'/login/sso?'.http_build_query([
            'error' => $message,
            'email' => $email,
            'is_warning' => $isWarning,
        ]));
    }

    #[Route(path: '/auth/google', name: 'google_login', methods: ['GET'])]
    public function googleSignIn(Request $request, UserContext $userContext, LoginHelper $loginHelper, Connection $database, string $dashboardUrl, GoogleLoginStrategy $strategy): Response
    {
        return $this->doOpenIdSignIn($request, $userContext, $loginHelper, $database, $dashboardUrl, $strategy, 'google_login');
    }

    #[Route(path: '/auth/intuit', name: 'intuit_login', methods: ['GET'])]
    public function intuitSignIn(Request $request, UserContext $userContext, LoginHelper $loginHelper, Connection $database, string $dashboardUrl, IntuitLoginStrategy $strategy): Response
    {
        return $this->doOpenIdSignIn($request, $userContext, $loginHelper, $database, $dashboardUrl, $strategy, 'intuit_login');
    }

    #[Route(path: '/auth/microsoft', name: 'microsoft_login', methods: ['GET'])]
    public function microsoftSignIn(Request $request, UserContext $userContext, LoginHelper $loginHelper, Connection $database, string $dashboardUrl, MicrosoftLoginStrategy $strategy): Response
    {
        return $this->doOpenIdSignIn($request, $userContext, $loginHelper, $database, $dashboardUrl, $strategy, 'microsoft_login');
    }

    #[Route(path: '/auth/xero', name: 'xero_login', methods: ['GET'])]
    public function xeroSignIn(Request $request, UserContext $userContext, LoginHelper $loginHelper, Connection $database, string $dashboardUrl, XeroLoginStrategy $strategy): Response
    {
        return $this->doOpenIdSignIn($request, $userContext, $loginHelper, $database, $dashboardUrl, $strategy, 'xero_login');
    }

    private function doOpenIdSignIn(Request $request, UserContext $userContext, LoginHelper $loginHelper, Connection $database, string $dashboardUrl, AbstractOpenIdLoginStrategy $strategy, string $retryPath): Response
    {
        try {
            if (!$request->query->has('code')) {
                if ($userContext->get()) {
                    $loginHelper->logout($request);
                }

                return $strategy->start();
            }

            $user = $strategy->authenticate($request);

            // If the user is not signed then 2FA verification is needed
            if (!$user->isFullySignedIn()) {
                return new RedirectResponse($dashboardUrl.'/verify-2fa', 302, [
                    // do not cache this page
                    'Cache-Control' => 'no-cache, no-store',
                ]);
            }

            // If the user was newly created then we need to create a company.
            $existing = $database->fetchOne('SELECT COUNT(*) FROM Members WHERE user_id = :userId', ['userId' => $user->id()]);
            if (0 == $existing) {
                return new RedirectResponse($dashboardUrl.'/setup/new', 302, [
                    // do not cache this page
                    'Cache-Control' => 'no-cache, no-store',
                ]);
            }

            $session = $request->getSession();
            if ($session->has('redirect_after_login')) {
                return new RedirectResponse($session->get('redirect_after_login'));
            }

            return new RedirectResponse($dashboardUrl, 302, [
                // do not cache this page
                'Cache-Control' => 'no-cache, no-store',
            ]);
        } catch (AuthException $e) {
            return $this->render('auth/signInFailure.twig', [
                'title' => 'Unable to sign in',
                'reason' => $e->getMessage(),
                'retryUrl' => $this->generateUrl($retryPath),
            ]);
        }
    }

    #[Route(path: '/verifyEmail/{id}', name: 'verify_email', methods: ['GET'], requirements: ['id' => '\w{24}'])]
    public function verifyEmail(string $dashboardUrl, EmailVerification $emailVerification, string $id): Response
    {
        $companyEmail = CompanyEmailAddress::queryWithoutMultitenancyUnsafe()
            ->where('token', $id)
            ->oneOrNull();
        if (!$companyEmail || $companyEmail->verified_at) {
            throw new NotFoundHttpException();
        }

        // check if the creator of the company has setup a password
        // if not, then prompt them for one
        $user = $companyEmail->tenant()->creator();
        if ($user && !$user->has_password) {
            return $this->render('auth/setPassword.twig', [
                'title' => 'Setup Password',
                'token' => $id,
                'errors' => [],
            ]);
        }

        // now we can complete the email verification
        $emailVerification->complete($companyEmail);

        return new RedirectResponse($dashboardUrl);
    }

    #[Route(path: '/verifyEmail/{id}', name: 'set_password', methods: ['POST'], requirements: ['id' => '\w{24}'])]
    public function setPassword(Request $request, string $dashboardUrl, Connection $database, UsernamePasswordLoginStrategy $strategy, EmailVerification $emailVerification, EventDispatcherInterface $eventDispatcher, StatsdClient $statsd, string $id): Response
    {
        $companyEmail = CompanyEmailAddress::queryWithoutMultitenancyUnsafe()
            ->where('token', $id)
            ->oneOrNull();
        if (!$companyEmail || $companyEmail->verified_at) {
            throw new NotFoundHttpException();
        }

        // check if the creator of the company has setup a password
        // if not, then prompt them for one
        $user = $companyEmail->tenant()->creator();
        if (!$user || $user->has_password) {
            throw new UnauthorizedHttpException('');
        }

        $errors = false;
        $password = (string) $request->request->get('password');

        // check the CSRF token
        if (!$this->isCsrfTokenValid('set_password', (string) $request->request->get('_csrf_token'))) {
            $statsd->increment('security.csrf_failure');
            $errors = ['Invalid CSRF token.'];
        } else {
            $user->password = $password;
            $user->has_password = true;
            if ($user->save()) {
                // Emit a changed password event on success
                $event = new ChangedPasswordEvent($user, $request);
                $eventDispatcher->dispatch($event);

                // Sign the user in again because changing password signs out
                try {
                    $strategy->login($request, $user->email, $password, true);
                } catch (AuthException) {
                    // ignore any sign in exceptions at this point
                }
            } else {
                $errors = $user->getErrors()->all();
            }
        }

        if ($errors) {
            $database->setRollbackOnly();

            return $this->render('auth/setPassword.twig', [
                'title' => 'Setup Password',
                'token' => $id,
                'errors' => $errors,
            ]);
        }

        // now we can complete the email verification
        $emailVerification->complete($companyEmail);

        return new RedirectResponse($dashboardUrl);
    }

    #[Route(path: '/logout', name: 'logout', methods: ['GET'], defaults: ['no_database_transaction' => true])]
    public function logout(Request $request, UserContext $userContext, LoginHelper $loginHelper): Response
    {
        $this->doLogout($request, $userContext, $loginHelper);

        return $this->redirect('/');
    }

    #[Route(path: '/auth/logout', name: 'dashboard_logout', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function dashboardLogout(Request $request, UserContext $userContext, LoginHelper $loginHelper): Response
    {
        $this->doLogout($request, $userContext, $loginHelper);

        return new Response('', 204);
    }

    private function doLogout(Request $request, UserContext $userContext, LoginHelper $loginHelper): void
    {
        $user = $userContext->get();
        if (!$user) {
            return;
        }

        if ($request->request->get('all')) {
            $loginHelper->signOutAllSessions($user);
        }

        $loginHelper->logout($request);

        // remove all active api keys for this user
        ApiKey::removeAllForUser($user);
    }

    #[Route(path: '/auth/account', name: 'dashboard_edit_account', methods: ['PATCH'])]
    public function editAccount(Request $request, UserContext $userContext, EditProtectedUserFields $editUser, Connection $database): JsonResponse
    {
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            return new JsonResponse(['type' => 'authorization_error'], 401);
        }

        try {
            if ($value = (string) $request->request->get('first_name')) {
                $user->first_name = $value;
            }
            if ($value = (string) $request->request->get('last_name')) {
                $user->last_name = $value;
            }
            if ($value = $request->request->getInt('default_company_id')) {
                $user->default_company_id = $value;
            }

            if (!$user->save()) {
                throw new AuthException('We were unable to update your account: '.$user->getErrors());
            }

            $protectedParameters = ['password', 'email'];
            $parameters = [
                'email' => $request->request->get('email'),
                'password' => $request->request->all('password'),
            ];
            $parameters = array_filter($parameters);

            if (count($parameters) > 0) {
                if (null !== $request->getSession()->get('company_restrictions')) {
                    throw new AuthException('We were unable to update your account: password/email can be changed only for non SSO users.');
                }
                $currentPassword = $request->request->getString('current_password');
                $editUser->change($user, $request, $currentPassword, $parameters);
            }

            return new JsonResponse($this->expandUser($user));
        } catch (AuthException $e) {
            $database->setRollbackOnly();

            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route(path: '/users/current/activity', name: 'dashboard_account_activity', methods: ['GET'])]
    public function accountActivity(UserContext $userContext, Request $request): JsonResponse
    {
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            return new JsonResponse(['type' => 'authorization_error', 401]);
        }

        $session = $request->getSession();
        if (null !== $session->get('company_restrictions')) {
            return new JsonResponse([
                'events' => [],
                'active_sessions' => [],
            ]);
        }

        $events = [];
        $_events = AccountSecurityEvent::where('user_id', $user->id())
            ->sort('id DESC')
            ->first(10);
        foreach ($_events as $event) {
            $events[] = $event->toArray();
        }

        $sessions = [];
        $_sessions = ActiveSession::where('user_id', $user->id())
            ->where('valid', true)
            ->where('expires', time(), '>=')
            ->all();
        $currentSessionId = $session->getId();
        foreach ($_sessions as $session) {
            $arr = $session->toArray();
            $arr['current'] = $session->id() == $currentSessionId;
            $sessions[] = $arr;
        }

        return new JsonResponse([
            'events' => $events,
            'active_sessions' => $sessions,
        ]);
    }

    #[Route(path: '/users/current/support_pin', name: 'user_support_pin', methods: ['GET'])]
    public function supportPin(UserContext $userContext): JsonResponse
    {
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            return new JsonResponse(['type' => 'authorization_error', 401]);
        }

        return new JsonResponse([
            'support_pin' => $user->getSupportPin(),
        ]);
    }

    #[Route(path: '/forgot', name: 'forgot_password_redirect', methods: ['GET'])]
    public function forgotPasswordRedirect(string $dashboardUrl): RedirectResponse
    {
        return new RedirectResponse($dashboardUrl.'/forgot');
    }

    #[Route(path: '/forgot/{id}', name: 'forgot_redirect', methods: ['GET'])]
    #[Route(path: '/users/forgot/{id}', name: 'forgot_redirect2', methods: ['GET'])]
    public function forgotPasswordLink(Request $request, string $dashboardUrl, UserContext $userContext, LoginHelper $loginHelper, ResetPassword $resetPassword, string $id): RedirectResponse
    {
        if ($userContext->get()) {
            $loginHelper->logout($request);
        }

        try {
            $resetPassword->getUserFromToken($id);
        } catch (AuthException) {
            throw new NotFoundHttpException();
        }

        return new RedirectResponse($dashboardUrl.'/#!/forgot/'.$id);
    }

    #[Route(path: '/auth/forgot', name: 'dashboard_forgot_password_step1', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function forgotPasswordStep1(Request $request, ResetPassword $resetPassword): Response
    {
        $email = (string) $request->request->get('email');
        $ip = (string) $request->getClientIp();
        $userAgent = (string) $request->headers->get('User-Agent');

        try {
            $resetPassword->step1($email, $ip, $userAgent);
        } catch (AuthException) {
            // In order to prevent username enumeration attacks
            // as mandated during a security audit, we are going
            // to show a non-matching email as successful.
            return new Response('', 204);
        }

        return new Response('', 204);
    }

    #[Route(path: '/auth/forgot/{token}', name: 'dashboard_forgot_password_step2', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function forgotPasswordStep2(Request $request, ResetPassword $resetPassword, string $token): Response
    {
        $password = $request->request->all('password');

        try {
            $resetPassword->step2($token, $password, $request);
        } catch (AuthException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        return new Response('', 204);
    }

    #[Route(path: '/auth/2fa/1', name: 'dashboard_enroll_2fa_step1', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function enroll2faStep1(Request $request, UserContext $userContext, AuthyVerification $strategy): Response
    {
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            return new JsonResponse(['type' => 'authorization_error'], 401);
        }

        if (null !== $request->getSession()->get('company_restrictions')) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => '2FA can not be modified for SSO users.',
            ], 400);
        }

        // register the user in Authy
        $phone = (string) $request->request->get('phone');
        $countryCode = $request->request->getInt('country_code');

        try {
            $strategy->register($user, $phone, $countryCode);
        } catch (AuthException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        return new Response('', 204);
    }

    #[Route(path: '/auth/2fa/2', name: 'dashboard_enroll_2fa_step2', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function enroll2faStep2(Request $request, TwoFactorHelper $twoFactor, UserContext $userContext, UsernamePasswordLoginStrategy $strategy): Response
    {
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            return new JsonResponse(['type' => 'authorization_error'], 401);
        }

        if (null !== $request->getSession()->get('company_restrictions')) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => '2FA can not be modified for SSO users.',
            ], 400);
        }

        // verify user's password
        $password = (string) $request->request->get('password');
        if (!$strategy->verifyPassword($user, $password)) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => 'Your password is not correct.',
            ]);
        }

        // verify the user's 2FA token
        return $this->verify2fa($request, $userContext, $twoFactor);
    }

    #[Route(path: '/auth/request_sms_2fa', name: 'dashboard_request_sms_2fa', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function requestSms2fa(UserContext $userContext, AuthyVerification $strategy): Response
    {
        $user = $userContext->get();
        if (!$user) {
            return new JsonResponse(['type' => 'authorization_error'], 401);
        } elseif (!$user->authy_id) {
            throw new NotFoundHttpException();
        }

        try {
            $strategy->requestSMS($user);
        } catch (AuthException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        return new Response('', 204);
    }

    #[Route(path: '/auth/verify_2fa', name: 'dashboard_verify_2fa', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function verify2fa(Request $request, UserContext $userContext, TwoFactorHelper $twoFactor): Response
    {
        $user = $userContext->get();
        if (!$user) {
            return new JsonResponse(['type' => 'authorization_error'], 401);
        } elseif (!$user->authy_id) {
            throw new NotFoundHttpException();
        }

        $token = (string) $request->request->get('token');
        $session = $request->getSession();
        $remember = (bool) $session->get('remember_me');

        try {
            $twoFactor->verify($request, $user, $token, $remember);
        } catch (AuthException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        if ($session->has('redirect_after_login')) {
            return new JsonResponse([
                'redirect_to' => $session->get('redirect_after_login'),
            ]);
        }

        return new Response('', 204);
    }

    #[Route(path: '/auth/remove_2fa', name: 'dashboard_remove_2fa', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function remove2fa(Request $request, UserContext $userContext, UsernamePasswordLoginStrategy $strategy, AuthyVerification $twoFactor): Response
    {
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            return new JsonResponse(['type' => 'authorization_error'], 401);
        }

        // verify user's password
        $password = (string) $request->request->get('password');
        if (!$strategy->verifyPassword($user, $password)) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => 'Your password is not correct.',
            ]);
        }

        // verify current token
        $token = (string) $request->request->get('token');

        try {
            $twoFactor->verify($user, $token);
        } catch (AuthException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        // remove the user from 2fa
        try {
            $twoFactor->deregister($user);
        } catch (AuthException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }

        return new Response('', 204);
    }

    private function currentUser(UserContext $userContext, SessionInterface $session): Response
    {
        $user = $userContext->get();

        if (!$user?->isFullySignedIn()) {
            // if the user ID is set then the user needs 2fa
            if ($user) {
                return new JsonResponse([
                    'two_factor_required' => true,
                ]);
            }

            return new JsonResponse(['type' => 'authorization_error'], 401);
        }

        // If this is part of an oauth authorization request
        if ($session->has('redirect_after_login')) {
            return new JsonResponse([
                'redirect_to' => $session->get('redirect_after_login'),
            ]);
        }

        return new JsonResponse($this->expandUser($user));
    }

    private function expandUser(User $user): array
    {
        $result = $user->toArray();
        $result['default_company_id'] = $user->default_company_id;

        return $result;
    }

    #[Route(path: '/oauth/authorize', name: 'oauth_authorize', methods: ['GET'])]
    public function oauthAuthorize(Request $request, UserContext $userContext, OAuthServerFactory $factory): Response
    {
        $server = $factory->getAuthorizationServer();
        $psrRequest = $factory->convertRequestToPsr($request);

        // Validate the HTTP request and return an AuthorizationRequest object.
        try {
            $authRequest = $server->validateAuthorizationRequest($psrRequest);
        } catch (OAuthServerException $e) {
            return $this->render('oauth/error.twig', [
                'message' => $e->getMessage(),
            ]);
        }

        // The auth request object can be serialized and saved into a user's session.
        // You will probably want to redirect the user at this point to a login endpoint.
        $session = $request->getSession();
        $session->set('oauth_authorization_request', $authRequest);

        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            $session->set('redirect_after_login', $this->generateUrl('oauth_confirm', [], UrlGeneratorInterface::ABSOLUTE_URL));

            return $this->redirectToRoute('login_redirect');
        }

        return $this->redirectToRoute('oauth_confirm');
    }

    #[Route(path: '/oauth/confirm', name: 'oauth_confirm', methods: ['GET', 'POST'])]
    public function oauthConfirm(Request $request, UserContext $userContext, ApproveOAuthApplication $approveAction): Response
    {
        $authRequest = $request->getSession()->get('oauth_authorization_request');
        if (!$authRequest instanceof AuthorizationRequest) {
            throw new NotFoundHttpException();
        }

        // At this point you should redirect the user to an authorization page.
        // This form will ask the user to approve the client and the scopes requested.
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            throw new NotFoundHttpException();
        }

        /** @var OAuthApplication $application */
        $application = $authRequest->getClient();

        $scopes = $authRequest->getScopes();
        $hasOpenId = false;
        foreach ($scopes as $scope) {
            if ('openid' == $scope->getIdentifier()) {
                $hasOpenId = true;
                break;
            }
        }

        if ($request->request->has('action')) {
            if ('approve' == $request->request->get('action')) {
                $authRequest->setAuthorizationApproved(true);

                // When the openid scope is requested then we are granting access to the user
                // Otherwise, access is granted to a tenant
                if ($hasOpenId) {
                    return $approveAction->approveForUser($user, $authRequest);
                }

                $company = Company::findOrFail($request->request->get('tenantId'));

                return $approveAction->approveForTenant($user, $company, $authRequest);
            }

            return $approveAction->deny($user, $authRequest);
        }

        // If this is not an OpenID request then let the user
        // choose which company to connect.
        $tenants = [];
        if (!$hasOpenId) {
            $companies = Company::where('name', '', '<>')
                ->where("EXISTS (SELECT 1 FROM Members WHERE user_id='{$user->id()}' AND `tenant_id`=Companies.id AND (expires = 0 OR expires > ".time().') GROUP BY `tenant_id` HAVING COUNT(*) > 0)')
                ->sort('name ASC')
                ->all();
            foreach ($companies as $company) {
                $tenants[] = [
                    'id' => $company->id(),
                    'name' => $company->nickname ?: $company->name,
                ];
            }

            if (0 == count($tenants)) {
                return $this->render('oauth/error.twig', [
                    'message' => 'Your user account is not associated with any Invoiced companies. If you should have access to an existing Invoiced account then please contact your Invoiced administrator.',
                ]);
            }
        }

        return $this->render('oauth/approve.twig', [
            'application' => $application,
            'scopes' => $scopes,
            'tenants' => $tenants,
        ]);
    }

    #[Route(path: '/oauth/access_token', name: 'oauth_access_token', methods: ['POST'])]
    public function oauthAccessToken(Request $request, OAuthServerFactory $factory): Response
    {
        try {
            $server = $factory->getAuthorizationServer();
            $psrRequest = $factory->convertRequestToPsr($request);
            $psrResponse = $server->respondToAccessTokenRequest(
                $psrRequest,
                $factory->convertResponseToPsr(new Response())
            );

            return (new HttpFoundationFactory())->createResponse($psrResponse);
        } catch (OAuthServerException $e) {
            return new JsonResponse([
                'type' => 'invalid_request',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route(path: '/oauth/revoke', name: 'oauth_revoke', methods: ['POST'])]
    public function oauthRevoke(Request $request, AccessTokenRepository $accessTokenRepository, string $oauthPublicKey): Response
    {
        $token = (string) $request->request->get('token');
        $jwt = JWT::decode($token, new Key($oauthPublicKey, 'RS256'));
        $accessTokenRepository->revokeAccessToken($jwt->jti);

        return new Response('', 204);
    }

    private function checkEditPermission(Company $company, UserContext $userContext): void
    {
        $user = $userContext->get();
        if (!$user) {
            throw new UnauthorizedHttpException('');
        }

        $member = Member::getForUser($user);
        if (!$member || !$company->memberCanEdit($member)) {
            throw new UnauthorizedHttpException('');
        }
    }
}
