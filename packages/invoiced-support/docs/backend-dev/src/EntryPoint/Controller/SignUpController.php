<?php

namespace App\EntryPoint\Controller;

use App\Companies\Enums\FraudOutcome;
use App\Companies\Enums\OnboardingStepType;
use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Exception\NewCompanySignUpException;
use App\Companies\Exception\OnboardingException;
use App\Companies\Libs\NewCompanySignUp;
use App\Companies\Libs\SignUpFraudDetector;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Models\CompanyNote;
use App\Companies\Models\Member;
use App\Companies\Onboarding\OnboardingFactory;
use App\Companies\Verification\EmailVerification;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Libs\UserRegistration;
use App\Core\Authentication\LoginStrategy\UsernamePasswordLoginStrategy;
use App\Core\Billing\Action\ActivatePurchasePageAction;
use App\Core\Billing\Action\ReactivatePurchasePageAction;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\I18n\Countries;
use App\Core\I18n\MoneyFormatter;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Network\Command\AcceptNetworkInvitation;
use App\Network\Exception\NetworkInviteException;
use App\Network\Models\NetworkInvitation;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\LocaleSwitcher;
use Throwable;
use Symfony\Component\String\ByteString;

#[Route(schemes: '%app.protocol%', host: '%app.domain%')]
class SignUpController extends AbstractController implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    #[Route(path: '/signup', name: 'signup_form', methods: ['GET'])]
    public function signupForm(Request $request, UserContext $userContext, LoginHelper $loginHelper, string $appDomain, string $environment): Response
    {
        if ($userContext->get()) {
            $loginHelper->logout($request);
        }

        // email
        $email = $request->query->get('email');
        if ($email2 = $request->request->get('email')) {
            $email = $email2;
        }

        // first name
        $firstName = $request->query->get('first_name');
        if ($firstName2 = $request->request->get('first_name')) {
            $firstName = $firstName2;
        }

        // last name
        $lastName = $request->query->get('last_name');
        if ($lastName2 = $request->request->get('last_name')) {
            $lastName = $lastName2;
        }

        // company name
        $companyName = $request->query->get('companyName');
        if ($companyName2 = $request->request->get('companyName')) {
            $companyName = $companyName2;
        }

        // country
        $country = $request->query->get('country');
        if ($country2 = $request->request->get('country')) {
            $country = $country2;
        }

        // referral partner
        $referredBy = $request->query->get('r');
        if (!$referredBy) {
            $referredBy = $request->request->get('r');
        }

        // invitation
        $invitation = $this->getInvitation($request);

        // free trial
        $freeTrial = !$invitation && 'sandbox' != $environment;

        return $this->renderWithUtm($request, $appDomain, 'auth/signup.twig', [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'companyName' => $companyName,
            'email' => $email,
            'countries' => $this->getCountries(),
            'selectedCountry' => $country,
            'reseller' => $referredBy,
            'invoices' => '',
            'invitation' => $invitation,
            'freeTrial' => $freeTrial,
        ]);
    }

    #[Route(path: '/signup', name: 'signup', methods: ['POST'])]
    public function signup(Request $request, SessionInterface $session, string $dashboardUrl, Connection $database, UserContext $userContext, LoginHelper $loginHelper, UserRegistration $userRegistration, UsernamePasswordLoginStrategy $strategy, TenantContext $tenant, string $environment, NewCompanySignUp $newCompanySignUp, SignUpFraudDetector $fraudDetector, string $appDomain, ReCaptcha $recaptcha, LoggerInterface $logger, AcceptNetworkInvitation $accept): Response
    {
        // record UTM parameters
        $utm = $request->cookies->get('utm_params');
        if ($utm) {
            $utm = json_decode($utm, true);
        }

        if (!is_array($utm)) {
            $utm = [];
        }

        try {
            // check the CSRF token
            if (!$this->isCsrfTokenValid('signup', (string) $request->request->get('_csrf_token'))) {
                $this->statsd->increment('security.csrf_failure');
                throw new NewCompanySignUpException('Invalid CSRF token.');
            }

            // verify recaptcha
            $captchaResp = (string) $request->request->get('g-recaptcha-response');
            if (!$captchaResp) {
                throw new NewCompanySignUpException('Please complete the captcha prompt.');
            }

            $resp = $recaptcha->verify($captchaResp, $request->getClientIp());
            if (!$resp->isSuccess()) {
                $logger->info('Captcha failure: '.implode(', ', $resp->getErrorCodes()));
                throw new NewCompanySignUpException('Captcha response failed.');
            }

            // validate the first name length is between 1 and 100 characters
            $firstName = trim((string) $request->request->get('first_name'));
            $firstNameLength = strlen($firstName);

            if ($firstNameLength < 1 || $firstNameLength > 100) {
                throw new NewCompanySignUpException('First name must be a string between 1 and 100 characters.');
            }

            $email = trim(strtolower((string) $request->request->get('email')));
            if (!str_contains($email, '@')) {
                throw new NewCompanySignUpException('Please enter a valid email address.');
            }

            $emailParts = explode('@', $email);
            [, $emailDomain] = $emailParts;

            // validate the email domain has an MX record
            if (!checkdnsrr($emailDomain.'.', 'MX')) {
                throw new NewCompanySignUpException('Please enter a valid email address.');
            }

            // validate the password length is at least 8 characters
            $password = trim((string) $request->request->get('password'));
            $passwordLength = mb_strlen($password);

            if ($passwordLength < 8) {
                throw new NewCompanySignUpException('Password must have at least 8 characters.');
            }

            // make sure the country was provided
            if (2 != strlen((string) $request->request->get('country'))) {
                throw new NewCompanySignUpException('Please select a country.');
            }

            // get the invitation
            $invitation = $this->getInvitation($request);

            // make sure the ToS was agreed to
            if ('1' != $request->request->get('agree_tos')) {
                throw new NewCompanySignUpException('Please agree to the Terms of Service and Privacy Policy.');
            }

            // build parameters for user and company
            $userParams = $this->getNewUserParams($request);
            $companyParams = $this->getNewCompanyParams($request, $environment);
            $requestParams = [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'accept_language' => $request->headers->get('Accept-Language'),
                'referer' => $request->headers->get('Referer'),
                'form_submission_time' => $request->request->get('delta'),
                'allow_vpn' => true, // Allow sign ups from IP addresses tagged as VPN
                'allow_hosting' => true, // Allow sign ups from IP addresses tagged as hosting
            ];

            // before creating anything perform a fraud detection check
            $outcome = $fraudDetector->evaluate($userParams, $companyParams, $requestParams);
            if (FraudOutcome::Block == $outcome->determination) {
                $database->setRollbackOnly();
                $this->logger->info($outcome->log);

                // show an internal server error
                return new Response('', 500);
            }

            if ($invitation) {
                $isAccountsPayable = $invitation->is_customer;
            } else {
                $isAccountsPayable = false;
            }

            // create the user
            try {
                $user = $userRegistration->registerUser($userParams, true, true);
            } catch (AuthException $e) {
                throw new NewCompanySignUpException($e->getMessage());
            }

            // create the company
            $companyParams['creator_id'] = $user->id();
            $changeset = $newCompanySignUp->getEntitlements($isAccountsPayable, $invitation instanceof NetworkInvitation);
            // Tag account if it needs a fraud review
            if (FraudOutcome::Warning == $outcome->determination) {
                $changeset = $changeset->withFeatures(['needs_fraud_review' => true]);
            }
            $company = $newCompanySignUp->create($companyParams, $changeset, $utm);
            // If a warning then save the fraud notes to the account
            if (FraudOutcome::Warning == $outcome->determination) {
                $note = new CompanyNote();
                $note->tenant_id = $company->id;
                $note->note = $outcome->log;
                $note->created_by = 'System';
                $note->save();
            }
        } catch (NewCompanySignUpException $e) {
            $database->setRollbackOnly();

            if ($e->getMessage() === 'Could not create user account: The Email you chose has already been taken. Please try a different Email.') {
                $session->set('signup_first_name', $request->request->get('first_name'));
                $session->set('signup_last_name', $request->request->get('last_name'));
                $session->set('signup_email', $request->request->get('email'));
                $id = strtolower(ByteString::fromRandom(24)->toString());

                return $this->redirectToRoute('verify_email_fake', [
                    'id' => $id,
                ]);
            } else {
                $this->addFlash('signup_error', $e->getMessage());
            }

            return $this->signupForm($request, $userContext, $loginHelper, $appDomain, $environment);
        } catch (Throwable $e) {
            $database->setRollbackOnly();
            $this->logger->error('Could not complete sign up', ['exception' => $e]);
            $this->addFlash('signup_error', 'An unknown error has occurred');

            return $this->signupForm($request, $userContext, $loginHelper, $appDomain, $environment);
        }

        // sign the user in
        try {
            $strategy->login($request, $email, $userParams['password'], true);
        } catch (AuthException) {
            // ignore any sign in exceptions at this point
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $company->useTimezone();

        if ($invitation) {
            $this->acceptInvitation($accept, $company, $invitation);
        }

        if ($company->features->has('needs_onboarding')) {
            return $this->redirectToRoute('onboarding_start', ['companyId' => $company->identifier]);
        }

        return new RedirectResponse($dashboardUrl);
    }

    #[Route('/onboarding/{id}/verify_email', name: 'verify_email_fake')]
    public function verifyEmailStep(Request $request, SessionInterface $session, string $id): Response
    {
        $firstName = $request->getSession()->get('signup_first_name');
        $lastName  = $request->getSession()->get('signup_last_name');

        $user = new class($firstName, $lastName) {
            public function __construct(
                private string $firstName,
                private string $lastName
            ) {}

            public function name(bool $full = false): string
            {
                return $full ? trim("$this->firstName $this->lastName") : $this->firstName;
            }
        };

        $request->attributes->set('companyId', $id);

        $errors = [];

        // Simulate error for submitting the code
        if ($request->isMethod('POST')) {
            $errors[] = 'The given code does not match the one sent to your email address. Please try again.';
        }

        return $this->render('onboarding/verify-email.twig', [
            'user' => $user,
            'currentStepNumber' => 2,
            'totalSteps' => 7,
            'errors' => $errors,
            'previousStep' => null,
            'company' => [
                'email' => $request->getSession()->get('signup_email'),
            ],
            'isFakeFlow' => true,
        ]);
    }

    #[Route('/onboarding/{companyId}/fakeResendVerificationEmail', name: 'onboarding_resend_verification_email_fake')]
    public function fakeResendVerificationEmail(Request $request, string $companyId): Response
    {
        $email = $request->query->get('email');

        $request->getSession()->getFlashBag()->add('verify_email', sprintf('A verification code has been resent to %s.', $email)); /* @phpstan-ignore-line */

        return $this->redirectToRoute('verify_email_fake', [
            'id' => $companyId,
        ]);
    }

    #[Route(path: '/onboarding/{companyId}', name: 'onboarding_start', methods: ['GET'])]
    public function onboardingStart(UserContext $userContext, TenantContext $tenant, OnboardingFactory $factory, string $dashboardUrl, string $companyId): Response
    {
        $company = Company::where('identifier', $companyId)->oneOrNull();
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        // Generate the onboarding state
        try {
            $state = $factory->buildState($company, null);
        } catch (OnboardingException $e) {
            return $this->redirect($dashboardUrl.'?'.http_build_query(['error' => $e->getMessage()]));
        }

        // Redirect to the current step
        return $this->redirectToRoute('onboarding_step', [
            'step' => $state->currentStep->value,
            'companyId' => $companyId,
        ]);
    }

    #[Route(path: '/onboarding/{companyId}/resendVerificationEmail', name: 'onboarding_resend_verification_email', methods: ['GET'])]
    public function resendVerificationEmail(TenantContext $tenant, EmailVerification $emailVerification, Request $request, string $companyId): Response
    {
        $company = Company::where('identifier', $companyId)->oneOrNull();
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $companyEmail = CompanyEmailAddress::queryWithTenant($company)
            ->where('email', $request->query->get('email'))
            ->oneOrNull();

        try {
            if ($companyEmail && !$companyEmail->verified_at) {
                $emailVerification->sendVerificationEmail($company, $companyEmail);
                $this->addFlash('verify_email', 'A verification email has been resent to '.$companyEmail->email);
            }
        } catch (BusinessVerificationException) {
            // do nothing
        }

        return $this->redirectToRoute('onboarding_step', [
            'step' => 'verify-email',
            'companyId' => $companyId,
        ]);
    }

    #[Route(path: '/onboarding/{companyId}/complete', name: 'onboarding_complete', methods: ['GET'])]
    public function onboardingComplete(string $dashboardUrl, UserContext $userContext, LocaleSwitcher $localeSwitcher, string $companyId): Response
    {
        $company = Company::where('identifier', $companyId)->oneOrNull();
        if (!$company) {
            throw new NotFoundHttpException();
        }

        $localeSwitcher->setLocale($company->getLocale());

        return $this->render('onboarding/finished.twig', [
            'company' => $company,
            'user' => $userContext->get(),
            'appUrl' => $dashboardUrl.'?account='.$company->id,
        ]);
    }

    #[Route(path: '/onboarding/{companyId}/{step}', name: 'onboarding_step', methods: ['GET', 'POST'])]
    public function onboardingStep(Request $request, UserContext $userContext, TenantContext $tenant, OnboardingFactory $factory, LocaleSwitcher $localeSwitcher, string $dashboardUrl, string $companyId, string $step): Response
    {
        $stepType = OnboardingStepType::tryFrom($step);
        if (!$stepType) {
            throw new NotFoundHttpException();
        }

        $company = Company::where('identifier', $companyId)->oneOrNull();
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        // Generate the onboarding state
        try {
            $state = $factory->buildState($company, $stepType);

            // Check if the current step can be visited
            $stepClass = $factory->get($stepType);
            if (!$stepClass->canRevisit($company)) {
                throw new OnboardingException('The current step cannot be revisited');
            }
        } catch (OnboardingException $e) {
            return $this->redirect($dashboardUrl.'?'.http_build_query(['error' => $e->getMessage()]));
        }

        // Handle a submitted form
        $errors = [];
        if ('POST' == $request->getMethod()) {
            // check the CSRF token
            if (!$this->isCsrfTokenValid('onboarding', (string) $request->request->get('_csrf_token'))) {
                throw new OnboardingException('Invalid CSRF token.', 'csrf');
            }

            try {
                $factory->get($stepType)->handleSubmit($company, $request);
                $this->statsd->increment('onboarding.complete_step', 1.0, ['step' => $step]);

                // Generate the onboarding state AFTER the step has been completed
                // because the current step can influence what the next step will be.
                $state = $factory->buildState($company, $stepType);

                // Redirect to next step, or to the application if complete
                if ($state->nextStep) {
                    return $this->redirectToRoute('onboarding_step', [
                        'step' => $state->nextStep->value,
                        'companyId' => $companyId,
                    ]);
                }
                // When the onboarding is complete then remove the feature flag
                $this->statsd->increment('onboarding.complete');
                $company->features->remove('needs_onboarding');

                return $this->redirectToRoute('onboarding_complete', ['companyId' => $companyId]);
            } catch (OnboardingException $e) {
                $this->statsd->increment('onboarding.fail_step', 1.0, ['step' => $step]);
                $errors[$e->field] = $e->getMessage();
            }
        } else {
            $this->statsd->increment('onboarding.view_step', 1.0, ['step' => $step]);
        }

        $localeSwitcher->setLocale($company->getLocale());

        // Render the form
        return $this->render('onboarding/'.$step.'.twig', [
            'returnUrl' => !$company->features->has('needs_onboarding') ? $dashboardUrl : null,
            'company' => $company,
            'user' => $userContext->get(),
            'countries' => $this->getCountries(),
            'errors' => $errors,
            'currentStepNumber' => $state->getCurrentStepNumber(),
            'totalSteps' => $state->getTotalSteps(),
            'previousStep' => $state->previousStep?->value,
            'isFakeFlow' => false,
        ]);
    }

    #[Route(path: '/activate', name: 'activate_form', methods: ['GET'])]
    public function activateForm(Request $request, TenantContext $tenant, UserContext $userContext, ReactivatePurchasePageAction $reactivatePurchasePageAction, ActivatePurchasePageAction $activatePurchasePageAction, string $dashboardUrl): Response
    {
        $companyId = $request->query->get('tenant_id');
        $company = Company::findOrFail($companyId);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        // Reactivation process for a canceled company
        if ($company->canceled) {
            // Check if the company can be auto-reactivated
            if ($reactivatePurchasePageAction->canAutoReactivate($company)) {
                $reactivatePurchasePageAction->autoReactivate($company);

                return $this->redirect($dashboardUrl);
            }

            // Show contact us message if the company is not eligible for self-service reactivation
            if (!$reactivatePurchasePageAction->canReactivate($company)) {
                return $this->render('billing/reactivateContactUs.twig');
            }

            // Generate purchase page and redirect to it
            $pageContext = $reactivatePurchasePageAction->makePage($company);

            return $this->redirectToRoute('purchase_page', ['id' => $pageContext->identifier]);
        }

        if (!$activatePurchasePageAction->canActivate($company)) {
            throw new NotFoundHttpException();
        }

        $this->statsd->increment('trial_funnel.view_plan_menu');

        // format the prices
        $prices = $activatePurchasePageAction->getAllPrices($company->country ?? 'US');
        $formatter = MoneyFormatter::get();
        foreach ($prices as &$row) {
            $row['price'] = $formatter->format($row['price'], ['precision' => 0]);
            $row['invoice'] = $formatter->format($row['invoice'], ['precision' => 2]);
            $row['user'] = $formatter->format($row['user'], ['precision' => 0]);
        }

        return $this->render('billing/activateConsolidated.twig', [
            'scheduleUrl' => 'https://www.invoiced.com/schedule-demo?'.http_build_query($request->query->all()),
            'prices' => $prices,
        ]);
    }

    #[Route(path: '/activate/select_plan', name: 'activate_select_plan', methods: ['GET'])]
    public function activateSelectPlan(TenantContext $tenant, Request $request, UserContext $userContext, ActivatePurchasePageAction $purchasePageAction): Response
    {
        $company = Company::findOrFail($request->query->get('tenant_id'));

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $this->checkEditPermission($company, $userContext);

        if (!$purchasePageAction->canActivate($company)) {
            throw new NotFoundHttpException();
        }

        $plan = (string) $request->query->get('plan');
        $billingInterval = 'yearly' == $request->query->get('billing_interval') ? BillingInterval::Yearly : BillingInterval::Monthly;
        $this->statsd->increment('trial_funnel.select_plan', 1.0, ['plan' => $plan, 'billing_interval' => $billingInterval->getIdName()]);

        // Generate purchase page and redirect to it
        $pageContext = $purchasePageAction->makePage($company, $plan, $billingInterval);

        return $this->redirectToRoute('purchase_page', ['id' => $pageContext->identifier]);
    }

    //
    // Helpers
    //

    private function saveUTM(Request $request, string $appDomain, array $utm = [], Response $response = null): Response
    {
        if (!$response) {
            $response = new Response();
        }

        // check for UTM parameters in the query
        $utmParams = [
            'utm_source',
            'utm_medium',
            'utm_term',
            'utm_content',
            'utm_campaign',
        ];

        foreach ($utmParams as $k) {
            if ($param = $request->query->get($k)) {
                $utm[$k] = $param;
            }
        }

        // save the parameters in the cookie
        if (count($utm) > 0) {
            // also set the referring URL
            if ($referer = $request->headers->get('referer')) {
                $utm['$initial_referrer'] = $referer;
                $utm['$initial_referring_domain'] = parse_url($referer, PHP_URL_HOST);
            }

            $domain = '.'.$appDomain;
            $cookie = new Cookie('utm_params', (string) json_encode($utm), time() + 90 * 86400, '/', $domain, false, false, false, Cookie::SAMESITE_LAX);
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    private function renderWithUtm(Request $request, string $appDomain, string $template, array $parameters = []): Response
    {
        $response = $this->saveUTM($request, $appDomain);

        return $this->render($template, $parameters, $response);
    }

    private function getNewUserParams(Request $request): array
    {
        return [
            'first_name' => $request->request->get('first_name'),
            'last_name' => $request->request->get('last_name'),
            'email' => $request->request->get('email'),
            'password' => $request->request->get('password'),
            'ip' => $request->getClientIp(),
        ];
    }

    private function getNewCompanyParams(Request $request, string $environment): array
    {
        $params = [
            'name' => $request->request->get('companyName') ?? '',
            'country' => $request->request->get('country'),
            'email' => $request->request->get('email'),
            'referred_by' => $request->request->get('r'),
        ];

        if ($timezone = $request->request->get('tz')) {
            $params['time_zone'] = $timezone;
        }

        if ('sandbox' == $environment) {
            $params['test_mode'] = true;
        } elseif (!$request->query->get('invitation')) {
            // Signing up in a non-sandbox environment without
            // an invitation creates a free trial.
            $params['trial_ends'] = CarbonImmutable::now()->addDays(30)->getTimestamp();
            $params['trial_started'] = time();
        }

        return $params;
    }

    private function getInvitation(Request $request): ?NetworkInvitation
    {
        $id = $request->query->get('invitation');
        if (!$id) {
            return null;
        }

        $invitation = NetworkInvitation::where('uuid', $id)
            ->where('expires_at', CarbonImmutable::now()->toDateTimeString(), '>')
            ->oneOrNull();
        if (!$invitation) {
            throw new NotFoundHttpException();
        }

        return $invitation;
    }

    private function acceptInvitation(AcceptNetworkInvitation $accept, Company $toCompany, NetworkInvitation $invitation): void
    {
        // Accept the invitation
        $invitation->to_company = $toCompany;
        $invitation->saveOrFail();

        try {
            $accept->accept($invitation);
        } catch (NetworkInviteException) {
            // do nothing
        }
    }

    private function checkEditPermission(Company $company, UserContext $userContext): void
    {
        $user = $userContext->get();
        if (!$user) {
            throw new NotFoundHttpException();
        }

        $member = Member::getForUser($user);
        if (!$member || !$company->memberCanEdit($member)) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * Returns a sorted list of countries.
     */
    private function getCountries(): array
    {
        $countriesData = new Countries();
        $countries = $countriesData->all();

        usort($countries, fn ($a, $b) => strcasecmp($a['country'], $b['country']));

        return $countries;
    }
}
