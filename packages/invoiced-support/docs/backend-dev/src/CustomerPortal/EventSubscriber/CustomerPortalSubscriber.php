<?php

namespace App\CustomerPortal\EventSubscriber;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\DomainRouter;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\AppUrl;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalContext;
use App\CustomerPortal\Libs\CustomerPortalFactory;
use App\CustomerPortal\Libs\CustomerPortalRateLimiter;
use App\CustomerPortal\Models\CspTrustedSite;
use App\CustomerPortal\Models\CustomerPortalSession;
use ParagonIE\CSPBuilder\CSPBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

class CustomerPortalSubscriber implements EventSubscriberInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    private bool $hasCsp = false;

    public function __construct(
        private Environment $twig,
        private string $projectDir,
        private string $environment,
        private CustomerPortalContext $customerPortal,
        private TenantContext $tenant,
        private string $appProtocol,
        private CustomerPortalRateLimiter $rateLimiter,
        private CustomerPortalFactory $customerPortalFactory,
        private UserContext $userContext,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $domain = $request->attributes->get('domain');

        if (DomainRouter::DOMAIN_TOKENIZATION === $domain) {
            return;
        }

        if (DomainRouter::DOMAIN_BILLING_PORTAL != $domain) {
            // This sends subdomains that cannot be a customer portal username but
            // do not actually exist in the application, i.e. mail.invoiced.com, to
            // a not found page.
            if (DomainRouter::DOMAIN_UNKNOWN == $domain) {
                throw new NotFoundHttpException();
            }

            return;
        }

        // see if this is a customer portal request
        $username = $request->attributes->get('subdomain');
        $portal = $this->customerPortalFactory->makeForUsername($username);

        if (!$portal) {
            $request->attributes->set('domain', DomainRouter::DOMAIN_UNKNOWN);

            throw new NotFoundHttpException();
        }

        // ensure HTTPS is used
        if (!$request->isSecure() && 'https' == $this->appProtocol) {
            $url = str_replace('http://', 'https://', $request->getUri());
            $response = new RedirectResponse($url, 301);
            $event->setResponse($response);

            return;
        }

        $this->customerPortal->set($portal);

        // IMPORTANT: set the current tenant for multitenant operations
        $company = $portal->company();
        $this->tenant->set($company);

        // Set the host on the request to the original company subdomain
        // so that our routes match
        $companySubdomain = $company->getSubdomainHostname();
        $request->headers->set('HOST', $companySubdomain);

        // check if allowed to access, and if not,
        // then require CAPTCHA verification to continue
        if ($this->rateLimiter->needsCaptchaVerification($company, (string) $request->getClientIp()) && !$this->rateLimiter->isAcmeRequest($request)) {
            $redirectUrl = $this->rateLimiter->encryptRedirectUrlParameter($request->getUri());
            $response = new RedirectResponse(AppUrl::get()->build().'/captcha?r='.$redirectUrl);
            $event->setResponse($response);

            return;
        }

        // determine the signed in user and email address from the customer portal session
        // note - this is a session layer separate from php sessions
        if ($session = $this->getSessionFromRequest($request)) {
            $portal->setSignedInEmail($session->email);
            $user = $session->user;
            if ($user) {
                $user->setIsFullySignedIn();
                $this->userContext->set($user);
            }
            $portal->setSignedInUser($user);
        }

        // determine the signed in customer
        $customer = $portal->getCustomerFromToken($this->getTokenFromRequest($request));
        if ($customer) {
            $portal->setSignedInCustomer($customer);
        }

        // set the current locale
        $this->determineLocale($request, $portal, $company, $customer);

        // set global parameters for templates
        $portal->setTwigGlobals($this->twig);

        $this->hasCsp = true;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->hasCsp) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        // add Content Security Policy headers to the response
        $file = $this->projectDir.'/config/contentSecurityPolicy/'.$this->environment.'/customerPortal.json';
        if (!file_exists($file)) {
            return;
        }

        // base configuration
        $csp = CSPBuilder::fromFile($file);

        // add customizations for the company
        /** @var CspTrustedSite[] $trustedSites */
        $trustedSites = CspTrustedSite::all();
        foreach ($trustedSites as $trustedSite) {
            foreach ($trustedSite->getEnabledSources() as $source) {
                $csp->addSource($source, $trustedSite->url);
            }
        }

        $response = $event->getResponse();
        $response->headers->add($csp->getHeaderArray());
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (DomainRouter::DOMAIN_BILLING_PORTAL != $request->attributes->get('domain')) {
            return;
        }

        $exception = $event->getThrowable();
        if ($exception instanceof NotFoundHttpException) {
            // override the 404 page when inside of a customer portal
            if ('json' == $request->getContentTypeFormat() || in_array('application/json', $request->getAcceptableContentTypes())) {
                $event->setResponse(new JsonResponse([
                    'error' => $exception->getMessage() ?: 'Page Not Found',
                    'message' => $exception->getMessage() ?: 'Page Not Found',
                ], 404));
            } else {
                $response = new Response($this->twig->render('customerPortal/404.twig', [
                    'title' => 'Not Found',
                ]), 404);
                $event->setResponse($response);
            }
        }

        if ($exception instanceof TokenNotFoundException) {
            $this->statsd->increment('security.csrf_failure');
            if ('json' == $request->getContentTypeFormat() || in_array('application/json', $request->getAcceptableContentTypes())) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Invalid CSRF token.',
                    'message' => 'Invalid CSRF token.',
                ], 400));
            } else {
                $response = new Response($this->twig->render('customerPortal/csrfError.twig', [
                    'title' => 'CSRF Check Failed',
                ]), 400);
                $event->setResponse($response);
            }
        }
    }

    /**
     * Gets a customer portal session from the request, if any.
     */
    private function getSessionFromRequest(Request $request): ?CustomerPortalSession
    {
        $sessionId = $request->cookies->get(CustomerPortalSession::COOKIE_NAME);
        if ($sessionId) {
            return CustomerPortalSession::getForIdentifier($sessionId);
        }

        return null;
    }

    /**
     * Gets the login token from the request.
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        return $request->cookies->get(SignInCustomer::COOKIE_NAME);
    }

    /**
     * Determines the locale to render the customer portal in.
     */
    private function determineLocale(Request $request, CustomerPortal $portal, Company $company, ?Customer $customer): void
    {
        $countryCode = $customer?->country;
        $countryCode = $countryCode ?: $company->country;

        // Locale can come from this order:
        // 1. ?locale query parameter (language switcher)
        // 2. locale memorized in session
        // 3. customer language setting
        // 4. viewer's preferred locale from their browser
        // 5. company default locale
        $preferredLocale = $request->getPreferredLanguage();
        $locale = $company->language.'_'.$countryCode;

        if ($queryLocale = $request->query->getString('locale')) {
            // The locale in the query parameter is the language only.
            // In order to get more accurate localization we will attempt
            // to add country code to locale from request.
            if (!str_contains($queryLocale, '_')) {
                $queryLocale .= '_'.$countryCode;
            }

            // Memorize the selected locale in the session
            $locale = $queryLocale;
            $request->getSession()->set('_locale', $locale);
        } elseif ($request->hasPreviousSession() && $sessionLocale = $request->getSession()->get('_locale')) {
            $locale = $sessionLocale;
        } elseif ($language = $customer?->language) {
            $locale = $language.'_'.$countryCode;
        } elseif ($preferredLocale && '*' != $preferredLocale) {
            $locale = $preferredLocale;
        }

        $this->setLocale($request, $portal, $locale);
    }

    private function setLocale(Request $request, CustomerPortal $portal, string $locale): void
    {
        try {
            $this->localeSwitcher->setLocale($locale);
            $request->setLocale($locale);
            $portal->setLocale($locale);
        } catch (InvalidArgumentException) {
            // do nothing on exception
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 35], // should be above router listener but after session authentication
            'kernel.response' => 'onKernelResponse',
            'kernel.exception' => ['onKernelException', -1], // should be after the exception listener logging
        ];
    }
}
