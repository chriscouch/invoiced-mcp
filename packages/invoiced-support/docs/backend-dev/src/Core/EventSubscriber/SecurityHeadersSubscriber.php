<?php

namespace App\Core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', "geolocation=(), microphone=(), camera=(), fullscreen=(self), payment=(self), usb=(), accelerometer=(), gyroscope=(), magnetometer=(), clipboard-read=(), clipboard-write=(self), display-capture=(), document-domain=()");
        $response->headers->set('Cross-Origin-Embedder-Policy', 'unsafe-none');
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
    }
}
