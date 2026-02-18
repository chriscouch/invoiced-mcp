<?php

namespace App\Core\Authentication\OAuth\EventSubscriber;

use App\Core\Authentication\OAuth\AccessTokenAuth;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class OAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AccessTokenAuth $auth
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->auth->isOAuthRequest($request)) {
            $this->auth->handleRequest($request);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 37], // should be above api listener
        ];
    }
}
