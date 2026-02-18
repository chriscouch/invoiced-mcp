<?php

namespace App\CustomerPortal\Command;

use App\AccountsReceivable\Models\Customer;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalContext;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\AppUrl;
use App\CustomerPortal\Models\CustomerPortalSession;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

class SignInCustomer implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    const string COOKIE_NAME = 'client';
    public const int TEMPORARY_SIGNED_IN_TTL = 86400; // 1 day, in seconds
    private const int SIGNED_IN_TTL = 1209600; // 14 days, in seconds

    public function __construct(
        private readonly CustomerPortalContext $customerPortal,
        private Environment $twig,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly CustomerPortalEvents $events
    ) {
    }

    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }

    /**
     * Signs in a customer.
     *
     * @param bool $temporary indicates whether this is a temporary sign in
     */
    public function signIn(Customer $customer, Response $response, bool $temporary = false): Response
    {
        // do not override an existing session
        /** @var CustomerPortal $customerPortal */
        $customerPortal = $this->customerPortal->get();
        if ($customerPortal->match($customer->id)) {
            return $response;
        }

        $ttl = ($temporary) ? self::TEMPORARY_SIGNED_IN_TTL : self::SIGNED_IN_TTL;

        // generate a new token now that the user is signed in
        $signedInToken = $customerPortal->generateLoginToken($customer, $ttl);

        // save the login token to a cookie
        // if temporary, only sign the user in for the session
        $secure = 'https' == AppUrl::get()->getProtocol();
        $cookieTtl = ($temporary) ? 0 : time() + self::SIGNED_IN_TTL;
        $cookie = new Cookie(self::COOKIE_NAME, $signedInToken, $cookieTtl, '/', '', $secure, true, false, $secure ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX);
        $response->headers->setCookie($cookie);

        $locale = $customer->getLocale();
        $customerPortal->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);
        $customerPortal->setSignedInCustomer($customer);
        $customerPortal->setTwigGlobals($this->twig);

        $this->statsd->increment('billing_portal.login');
        $this->events->track($customer, CustomerPortalEvent::Login);

        return $response;
    }

    /**
     * Signs the current user out of the customer portal.
     */
    public function signOut(Response $response): Response
    {
        // Clear signed in customer cookie
        $secure = 'https' == AppUrl::get()->getProtocol();
        $cookie = new Cookie(self::COOKIE_NAME, '', time() - 86400 * 365, '/', '', $secure, true, false, $secure ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX);
        $response->headers->setCookie($cookie);

        // Clear customer portal session cookie
        $cookie = new Cookie(CustomerPortalSession::COOKIE_NAME, '', time() - 86400 * 365, '/', '', $secure, true, false, $secure ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX);
        $response->headers->setCookie($cookie);

        $this->statsd->increment('billing_portal.logout');

        return $response;
    }
}
