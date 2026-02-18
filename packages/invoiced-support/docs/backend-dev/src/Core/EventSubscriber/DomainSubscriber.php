<?php

namespace App\Core\EventSubscriber;

use App\Core\DomainRouter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class DomainSubscriber implements EventSubscriberInterface
{
    public function __construct(private DomainRouter $domainRouter)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // loads the right set of routes based on the requested hostname,
        // i.e. landing, API, customer portal
        $request = $event->getRequest();
        $this->domainRouter->route($request);
    }

    public static function getSubscribedEvents(): array
    {
        return [
           'kernel.request' => ['onKernelRequest', 255], // This should run before any other request listeners, except the validate request and debug listeners
        ];
    }
}
