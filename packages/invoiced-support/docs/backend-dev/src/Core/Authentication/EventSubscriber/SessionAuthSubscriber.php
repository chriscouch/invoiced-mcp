<?php

namespace App\Core\Authentication\EventSubscriber;

use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Libs\UserContext;
use App\Core\DomainRouter;
use App\Core\Orm\ACLModelRequester;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class SessionAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserContext $userContext,
        private LoginHelper $loginHelper
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // The API, redirect, and unknown domains do not need to
        // call the auth middleware because they do not have sessions.
        $request = $event->getRequest();
        $domain = $request->attributes->get('domain');
        if (in_array($domain, [DomainRouter::DOMAIN_API, DomainRouter::DOMAIN_UNKNOWN])) {
            return;
        }

        // check for a custom session expiration
        if (!headers_sent() && PHP_SESSION_ACTIVE !== session_status() && $lifetime = (int) $request->cookies->get('SessionLifetime')) {
            ini_set('session.gc_maxlifetime', (string) $lifetime);
            session_set_cookie_params($lifetime);
        }

        // set callback for orm to obtain current user
        ACLModelRequester::setCallable(fn () => $this->userContext->get());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // The API, redirect, and unknown domains do not need to
        // call the auth middleware because they do not have sessions.
        $request = $event->getRequest();
        $domain = $request->attributes->get('domain');
        if (in_array($domain, [DomainRouter::DOMAIN_API, DomainRouter::DOMAIN_UNKNOWN])) {
            return;
        }

        // Check if a session was started
        if (!$request->hasSession()) {
            return;
        }

        // Add any cookies to the response
        $cookieBag = $this->loginHelper->getCookieBag();
        if (0 == count($cookieBag)) {
            return;
        }

        $response = $event->getResponse();
        foreach ($cookieBag->all() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
           'kernel.request' => ['onKernelRequest', 36], // should be above router listener
            'kernel.response' => 'onKernelResponse',
        ];
    }
}
