<?php

namespace App\Core;

use App\Companies\Models\Company;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Storage\InMemoryStorage;
use Symfony\Component\HttpFoundation\Request;

class DomainRouter
{
    const DOMAIN_MAIN = 'main';
    const DOMAIN_API = 'api';
    const DOMAIN_BILLING_PORTAL = 'billing_portal';
    const DOMAIN_TOKENIZATION = 'tknz';
    const DOMAIN_FILES = 'files';
    const DOMAIN_UNKNOWN = 'unknown';
    const CUSTOM_DOMAIN_PREFIX = 'custom:';

    public function __construct(
        private LoginHelper $loginHelper,
        private string $environment,
        private string $appDomain,
    ) {
    }

    /**
     * Sets up the application to handle requests based on the
     * requested hostname. Depending on the domain, a unique
     * routing table will be installed. This method should be
     * called for incoming web requests, before the application
     * starts to handle the request. In this sense the domain
     * router can be thought of as a meta-router.
     */
    public function route(Request $request): void
    {
        [$domain, $subdomain] = $this->determineRouteFromHost($request->getHost());

        // set domain route info as request params
        $request->attributes->add([
            'domain' => $domain,
            'subdomain' => $subdomain,
        ]);

        // extend session lifetime in customer portal to prevent
        // pesky CSRF prevention errors from customers leaving
        // pages open for > 30 minutes
        if (!headers_sent() && PHP_SESSION_ACTIVE !== session_status()) {
            if (self::DOMAIN_BILLING_PORTAL == $domain) {
                ini_set('session.gc_maxlifetime', '86400'); // 1 day
                session_set_cookie_params(86400); // 1 day
            } else {
                ini_set('session.gc_maxlifetime', '1800'); // 30 minutes
                session_set_cookie_params(1800); // 30 minutes
            }
        }

        // disable sessions on API
        if (self::DOMAIN_API == $domain) {
            $this->loginHelper->setStorage(new InMemoryStorage());
        }
    }

    /**
     * Determines the route to use based on the requested hostname.
     */
    public function determineRouteFromHost(string $hostname): array
    {
        // check if the primary domain is being requested
        if ($hostname == $this->appDomain || 0 === strlen($hostname)) {
            return [self::DOMAIN_MAIN, false];
        }

        // assume ngrok is directed at the main domain (development only)
        if ('dev' == $this->environment && str_contains($hostname, 'ngrok.io')) {
            return [self::DOMAIN_MAIN, false];
        }


        // handle custom domains
        if (str_starts_with($hostname, self::DOMAIN_TOKENIZATION)) {
            return [self::DOMAIN_TOKENIZATION, $hostname];
        }

        // handle custom domains
        if (str_starts_with($hostname, self::DOMAIN_FILES)) {
            return [self::DOMAIN_FILES, $hostname];
        }

        // handle custom domains
        if (!str_contains($hostname, $this->appDomain)) {
            return [self::DOMAIN_BILLING_PORTAL, self::CUSTOM_DOMAIN_PREFIX.$hostname];
        }

        $subdomain = strtolower(explode('.', $hostname)[0]);

        // handle API requests
        if ('api' === $subdomain) {
            return [self::DOMAIN_API, $subdomain];
        }

        // handle customer portal requests
        if (Company::validateUsername($subdomain)) {
            return [self::DOMAIN_BILLING_PORTAL, $subdomain];
        }

        // otherwise, the domain is unrecognized
        return [self::DOMAIN_UNKNOWN, $subdomain];
    }
}
