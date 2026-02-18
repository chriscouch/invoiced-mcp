<?php

namespace App\Core\EventSubscriber;

use App\Core\Utils\DebugContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class RequestIdSubscriber implements EventSubscriberInterface
{
    public function __construct(private DebugContext $debugContext)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->debugContext->generateRequestId();
        if ($requestId = $request->headers->get('X-Request-Id')) {
            $this->debugContext->setRequestId($requestId);
        }
        if ($correlationId = $request->headers->get('X-Correlation-Id')) {
            $this->debugContext->setCorrelationId($correlationId);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // returns the request ID and correlation ID in the response
        $response->headers->set('X-Request-Id', (string) $this->debugContext->getRequestId());
        $response->headers->set('X-Correlation-Id', $this->debugContext->getCorrelationId());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 254], // should come first after domain router
            'kernel.response' => ['onKernelResponse', 256], // should be first
        ];
    }
}
