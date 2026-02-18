<?php

namespace App\Tokenization\EventSubscriber;

use App\Core\Authentication\Exception\AuthException;
use App\Core\DomainRouter;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Tokenization\Models\PublishableKey;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class TokenizationSubscriber implements EventSubscriberInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private readonly TenantContext $tenantContext) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (DomainRouter::DOMAIN_TOKENIZATION != $request->attributes->get('domain')) {
            return;
        }

        // Only set headers on API routes that require authentication
        $request = $event->getRequest();

        // Set headers
        $response = $event->getResponse();
        $this->setHeaders($request, $response);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (DomainRouter::DOMAIN_TOKENIZATION != $request->attributes->get('domain')) {
            return;
        }

        // Only set headers on API routes that require authentication
        $request = $event->getRequest();

        $authHeader = $request->headers->get('Authorization');
        if (null === $authHeader) {
            throw new AuthException( 'Authentication Failed');
        }

        // Check if it's a Basic auth header
        if (!str_starts_with($authHeader, 'Basic ')) {
            throw new AuthException( 'Unsupported authentication schema');
        }

        // Extract the Base64-encoded part
        $encodedCredentials = substr($authHeader, 6);
        $decodedCredentials = base64_decode($encodedCredentials);
        if (!str_starts_with($decodedCredentials, ':')) {
            throw new AuthException( 'Unsupported authentication schema');
        }
        list($_, $password) = explode(':', $decodedCredentials, 2);

        if (!$key = PublishableKey::queryWithoutMultitenancyUnsafe()
            ->where('secret',$password)
            ->oneOrNull()) {
            throw new AuthException( 'Authentication Failed');
        }

        $tenant = $key->tenant();
        $this->tenantContext->set($tenant);
    }


    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.response' => 'onKernelResponse',
            'kernel.request' => 'onKernelRequest',
            'kernel.exception' => ['onKernelException', -1],
        ];
    }

    private function setHeaders(Request $request, Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('origin', '*'));
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization,Content-Type,Accept,Origin,User-Agent');
        $response->headers->set('Access-Control-Max-Age', '1728000');
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (DomainRouter::DOMAIN_TOKENIZATION != $request->attributes->get('domain')) {
            return;
        }
        $e = $event->getThrowable();
        if ($e instanceof AuthException) {
            $this->statsd->increment('payments.failed_api_auth.v2');
            $response = new JsonResponse([
                'message' => $e->getMessage()
            ], 403);
        } else {
            $response = new JsonResponse([
                'message' => 'Unexpected Error happened'
            ], 500);
        }
        $this->setHeaders($request, $response);
        $event->setResponse($response);
    }
}
