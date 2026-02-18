<?php

namespace App\CustomerPortal\Libs\LoginSchemes;

use App\Companies\Models\Company;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\SessionHelpers\CustomerPortalEmailSessionHelper;
use App\CustomerPortal\Models\CustomerPortalSession;
use App\Sending\Email\EmailFactory\CompanyEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Libs\EmailHtml;
use App\Sending\Email\Libs\EmailSpool;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Allows customers to sign in using just an email address.
 * It works by sending a magic link to the given email address
 * that will start a new customer portal session.
 */
class EmailLoginScheme implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private const int DEBOUNCE_TTL = 300; // 5 minutes

    public function __construct(
        private RateLimiterFactory $customerPortalLoginLimiter,
        private readonly LockFactory $lockFactory,
        private readonly string $cacheNamespace,
        private readonly EmailSpool $emailSpool,
        private readonly CompanyEmailFactory $emailFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Sends a magic link to the given email address that
     * signs the email address into the customer portal.
     */
    public function requestLogin(CustomerPortal $customerPortal, string $email, string $ip, ?string $redirectTo = null): bool
    {
        $company = $customerPortal->company();

        // Check for a rate limit violation for the IP address
        $usernameLimiter = $this->customerPortalLoginLimiter->create($customerPortal->company()->id.'_'.$ip);
        $limit = $usernameLimiter->consume();
        if (!$limit->isAccepted()) {
            $this->statsd->increment('billing_portal.login_request_fail');

            return false;
        }

        // debounce sign in link requests to the same email address within 5 minutes
        $lock = $this->getEmailLock($company, $email);
        if (!$lock) {
            $this->statsd->increment('billing_portal.login_request_fail');

            return false;
        }

        // generate a new customer portal session
        $session = (new CustomerPortalEmailSessionHelper($email))->upsertSession();

        // send the sign in email
        try {
            $this->sendEmail($company, $email, $session, $redirectTo);
            $this->statsd->increment('billing_portal.login_request');

            return true;
        } catch (SendEmailException) {
            // errors here are ignored but release the lock so the user can try again
            $this->statsd->increment('billing_portal.login_request_fail');
            $lock->release();

            return false;
        }
    }

    private function getEmailLock(Company $company, string $email): ?LockInterface
    {
        $debounceKey = $this->getDebounceKey($company, $email);
        $lock = $this->lockFactory->createLock($debounceKey, self::DEBOUNCE_TTL, false);

        try {
            if (!$lock->acquire()) {
                return null;
            }
        } catch (LockAcquiringException $e) {
            // Fail open so Redis outages don't take down the login page
            $this->logger->error('Failed to acquire customer portal email login debouncing lock', ['exception' => $e]);
        }

        return $lock;
    }

    private function getDebounceKey(Company $company, string $email): string
    {
        return $this->cacheNamespace.':client_login_email.'.$company->id().'.'.$email;
    }

    /**
     * @throws SendEmailException
     */
    private function sendEmail(Company $company, string $email, CustomerPortalSession $session, ?string $redirectTo): void
    {
        // generate the "Sign In" button
        $url = $this->makeSignInUrl($company, $session, $redirectTo);
        $buttonText = 'Sign In';
        $button = EmailHtml::button($buttonText, $url, '#54BF83', false);

        $templateVars = [
            'button' => $button,
            'url' => $url,
            'name' => $company->getDisplayName(),
        ];
        $to = [['email' => $email]];
        $subject = 'Sign in to our customer portal';

        $email = $this->emailFactory->make($company, 'login-request', $templateVars, $to, $subject);
        $this->emailSpool->spool($email);
    }

    private function makeSignInUrl(Company $company, CustomerPortalSession $session, ?string $redirectTo): string
    {
        // As soon as the user signs in, redirect immediately to the select customer page.
        // Once they select a customer (or if there's only 1 available) then this will
        // redirect to the redirectTo parameter, or to the My Account page if not provided.
        $redirectUrl = $this->urlGenerator->generate('customer_portal_select_customer', [
            'subdomain' => $company->getSubdomainUsername(),
            'redirect_to' => $redirectTo,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $redirectUrl = $this->applyCustomDomain($company, $redirectUrl);

        // This is the URL to start the customer portal session that
        // should be used as the "Sign In" button in the email.
        $url = $this->urlGenerator->generate('customer_portal_start_session', [
            'subdomain' => $company->getSubdomainUsername(),
            'id' => $session->identifier,
            'r' => base64_encode($redirectUrl),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->applyCustomDomain($company, $url);
    }

    /**
     * Swaps customer portal subdomain for custom domain.
     */
    private function applyCustomDomain(Company $company, string $url): string
    {
        if ($domain = $company->custom_domain) {
            $start = strpos($url, '//');
            $end = (int) strpos($url, '/', $start + 2);
            $url = substr_replace($url, "https://$domain", 0, $end);
        }

        return $url;
    }
}
