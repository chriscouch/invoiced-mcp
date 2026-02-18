<?php

namespace App\Core\EventSubscriber;

use App\Core\DomainRouter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class CorsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private string $dashboardUrl,
        private string $environment,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $domain = $request->attributes->get('domain');
        if (DomainRouter::DOMAIN_MAIN === $domain) {
            // In staging we use many different domains for the frontend
            // and want to allow these domains to make cross-origin requests.
            // See RFC 6454 for information on Origin header.
            if ('staging' == $this->environment && $origin = (string) $request->headers->get('Origin')) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            } else {
                $response->headers->set('Access-Control-Allow-Origin', $this->dashboardUrl);
            }

            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // security headers
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        if (!in_array($domain, [DomainRouter::DOMAIN_API, DomainRouter::DOMAIN_BILLING_PORTAL])) {
            $response->headers->set('X-Frame-Options', 'DENY');
        }
        if (DomainRouter::DOMAIN_API != $domain) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
           'kernel.response' => 'onKernelResponse',
        ];
    }
}
