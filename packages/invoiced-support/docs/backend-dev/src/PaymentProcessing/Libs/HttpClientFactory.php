<?php

namespace App\PaymentProcessing\Libs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Makes Guzzle HTTP clients that are used by our application.
 * This applies our standard HTTP client configuration, retry logic,
 * and response logging.
 */
class HttpClientFactory
{
    private const CONNECT_TIMEOUT = 10;
    private const READ_TIMEOUT = 35;
    private const MAX_RETRIES = 3;

    private GatewayLogger $gatewayLogger;

    public function __construct(GatewayLogger $gatewayLogger)
    {
        $this->gatewayLogger = $gatewayLogger;
    }

    public static function make(GatewayLogger $gatewayLogger, array $config = []): Client
    {
        $handlerStack = HandlerStack::create(new CurlHandler());
        $handler = new self($gatewayLogger);
        $handlerStack->push(Middleware::retry([$handler, 'retryDecider'], [$handler, 'retryDelay']));
        $handlerStack->push(Middleware::mapResponse([$handler, 'logResponse']));

        $config = array_replace([
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'read_timeout' => self::READ_TIMEOUT,
            'handler' => $handlerStack,
        ], $config);

        if (!isset($config['headers'])) {
            $config['headers'] = [];
        }
        $config['headers']['User-Agent'] = 'Invoiced/1.0';

        return new Client($config);
    }

    /**
     * @param mixed $response
     * @param mixed $exception
     */
    public function retryDecider(
        int $retries,
        RequestInterface $request,
        $response,
        $exception
    ): bool {
        // Limit the number of retries
        if ($retries >= self::MAX_RETRIES) {
            return false;
        }

        // Retry connection exceptions
        if ($exception instanceof ConnectException) {
            return true;
        }

        // Retry on server errors
        if ($exception instanceof ServerException) {
            return true;
        }

        return false;
    }

    public function retryDelay(int $numberOfRetries): int
    {
        return 1000 * $numberOfRetries;
    }

    public function logResponse(ResponseInterface $response): ResponseInterface
    {
        $this->gatewayLogger->logHttpResponse($response);

        return $response;
    }
}
