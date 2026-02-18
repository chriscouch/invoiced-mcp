<?php

namespace App\Core\RestApi\EventSubscriber;

use App\Core\RestApi\Encoders\JsonEncoder;
use App\Core\RestApi\Exception\ApiHttpException;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Interfaces\RateLimiterInterface;
use App\Core\RestApi\Libs\ApiIdempotencyKey;
use App\Core\RestApi\Libs\ApiKeyAuth;
use App\Core\RestApi\Libs\ConcurrentRateLimiter;
use App\Core\RestApi\Libs\RequestLogger;
use App\Core\DomainRouter;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ApiSubscriber implements EventSubscriberInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    /** @var RateLimiterInterface[] */
    private array $rateLimiters = [];
    private bool $isAuthenticated = false;
    private bool $shouldCacheResponse = false;

    public function __construct(
        private ApiKeyAuth $apiKeyAuth,
        private Stopwatch $stopwatch,
        private RequestLogger $requestLogger,
        private JsonEncoder $jsonEncoder,
        private ApiIdempotencyKey $idempotencyKey,
        private DebugContext $debugContext,
        ConcurrentRateLimiter $concurrentRateLimiter,
    ) {
        $this->rateLimiters[] = $concurrentRateLimiter;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Check if API route requires key authentication
        $request = $event->getRequest();
        if (!$this->requiresAuthentication($request)) {
            return;
        }

        // Time the request
        $this->stopwatch->start('api');

        // 1. Authenticate
        $this->authenticate($request);

        // 2. Rate limit
        $this->rateLimit($request);
        $this->isAuthenticated = true;

        // 3. Check for an idempotency key and return cached response
        if ($cachedResponse = $this->idempotency($request)) {
            $this->setHeaders($request, $cachedResponse);
            $event->setResponse($cachedResponse);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Only set headers on API routes that require authentication
        $request = $event->getRequest();
        if (!$this->requiresAuthentication($request)) {
            return;
        }

        // Set headers
        $response = $event->getResponse();
        $this->setHeaders($request, $response);

        // Time the response time. This does not cover the time spent in Symfony prior
        // to the kernel.request event in the API subscriber and
        // after the kernel.response event in the API subscriber. We
        // are only trying to measure the time spent in our API controllers
        // in order to get less noise.
        if ($this->stopwatch->isStarted('api')) {
            $this->stopwatch->stop('api');
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (DomainRouter::DOMAIN_API != $request->attributes->get('domain')) {
            return;
        }

        $exception = $event->getThrowable();
        $request = $event->getRequest();

        $message = null;
        $param = null;

        if ($exception instanceof ApiHttpException) {
            $code = $exception->getStatusCode();
            $errorType = $exception->getErrorType();
            $message = $exception->getMessage();
            if ($exception instanceof InvalidRequest) {
                $param = $exception->getParam();
            }
        } elseif ($exception instanceof HttpException) {
            $code = $exception->getStatusCode();
            $errorType = 'invalid_request';
            $message = $this->getErrorMessage($code, $request, $exception->getMessage());
            // do not log if this is due to any routing exceptions
            if (($exception instanceof NotFoundHttpException && $exception->getPrevious() instanceof ExceptionInterface) || $exception instanceof MethodNotAllowedHttpException) {
                $request->attributes->set('_doNotLogApiRequest', true);
            }
        } else {
            $code = 500;
            $errorType = 'api_error';
        }

        if (!$message) {
            $message = $this->getErrorMessage($code, $request);
        }

        $result = [
            'type' => $errorType,
            'message' => $message,
        ];

        if ($param) {
            $result['param'] = $param;
        }

        $response = $this->jsonEncoder->encode($result, new Response('', $code));
        $this->setHeaders($request, $response);
        $event->setResponse($response);
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Caching, logging, and rate limiting only happen
        // for authenticated API requests.
        if (DomainRouter::DOMAIN_API != $request->attributes->get('domain') || !$this->isAuthenticated) {
            return;
        }

        $response = $event->getResponse();

        // cache the response
        if ($this->shouldCacheResponse) {
            $this->idempotencyKey->cacheResponse($response);
        }

        // log the request
        $apiKey = $this->apiKeyAuth->getCurrentApiKey();
        $responseTime = $this->stopwatch->getEvent('api')->getDuration();
        $this->requestLogger
            ->log($request, $response, $responseTime, $apiKey)
            ->flush();

        if ($apiKey) {
            // update the key last used and expiration
            $this->apiKeyAuth->updateKeyUsage($apiKey);

            // clean up the rate limiters
            foreach ($this->rateLimiters as $rateLimiter) {
                $rateLimiter->cleanUpAfterRequest((string) $apiKey->id());
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => ['onKernelRequest', 36], // should be above router listener
            'kernel.response' => 'onKernelResponse',
            'kernel.exception' => ['onKernelException', -1], // should be after the exception listener logging
            'kernel.terminate' => 'onKernelTerminate',
        ];
    }

    //
    // Authentication
    //

    /**
     * Checks if a request requires authentication.
     */
    private function requiresAuthentication(Request $request): bool
    {
        if (DomainRouter::DOMAIN_API != $request->attributes->get('domain')) {
            return false;
        }

        $path = $request->getPathInfo();
        if ('/health' == $path || str_starts_with($path, '/_metadata') || str_starts_with($path, '/_profiler')) {
            return false;
        }

        return true;
    }

    private function authenticate(Request $request): void
    {
        // Authentication can be skipped if another authentication method
        // was used, like an OAuth access token.
        if ($request->attributes->get('skip_api_authentication')) {
            return;
        }

        try {
            $this->apiKeyAuth->handleRequest($request);
        } catch (ApiHttpException $e) {
            $this->statsd->increment('security.failed_api_auth');

            throw $e;
        }
    }

    private function setHeaders(Request $request, Response $response): void
    {
        // do not cache API calls
        $response->headers->set('Cache-Control', 'no-cache, no-store');

        // request HTTP Basic Authentication unless this is a dashboard API call
        if (!$request->headers->has('X-App-Version')) {
            $response->headers->set('WWW-Authenticate', 'Basic realm="Invoiced"');
        }

        // all responses are JSON unless specified
        if (!$response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'application/json');
        }
    }

    //
    // Rate Limiting
    //

    /**
     * Returns a rate limited response if a request should be rate limited.
     *
     * @throws ApiHttpException when the request should be rate limited
     */
    public function rateLimit(Request $request): void
    {
        // only perform rate limiting on API routes
        // where an API key was supplied (excludes dashboard routes)
        $apiKey = $this->apiKeyAuth->getCurrentApiKey();
        if (!$apiKey || $apiKey->user()) {
            return;
        }

        foreach ($this->rateLimiters as $rateLimiter) {
            if (!$rateLimiter->isAllowed((string) $apiKey->id())) {
                $this->statsd->increment('api.call.'.strtolower($request->getMethod()));
                $this->statsd->increment('api.response.429');

                throw new ApiHttpException(429, $rateLimiter->getErrorMessage());
            }
        }
    }

    //
    // Idempotency
    //

    public function idempotency(Request $request): ?Response
    {
        // only allow idempotency keys on api routes
        // where an API key was supplied
        $apiKey = $this->apiKeyAuth->getCurrentApiKey();
        if (!$apiKey) {
            return null;
        }

        // retrieve and validate the user-supplied idempotency key
        $key = ApiIdempotencyKey::getKeyFromRequest($request);
        if (!$key) {
            return null;
        }

        $this->idempotencyKey->setKey($apiKey, $key);

        $response = $this->idempotencyKey->getCachedResponse();
        if (!$response) {
            $this->shouldCacheResponse = true;
        }

        return $response;
    }

    //
    // Error Responses
    //

    /**
     * Gets the API error message.
     */
    private function getErrorMessage(int $code, Request $request, ?string $defaultMessage = null): string
    {
        if (404 == $code) {
            return 'Request was not recognized: '.$request->getMethod().' '.$request->getPathInfo();
        } elseif (405 == $code) {
            return 'Method not allowed: '.$request->getMethod().' '.$request->getPathInfo();
        } elseif (500 == $code) {
            return "There was an error processing your request. Our engineers have been notified and will be looking into it soon. If you would like help then you can reach us at support@invoiced.com.\n\nCorrelation ID: ".$this->debugContext->getCorrelationId();
        }

        if ($defaultMessage) {
            return $defaultMessage;
        }

        return Response::$statusTexts[$code] ?? '';
    }
}
