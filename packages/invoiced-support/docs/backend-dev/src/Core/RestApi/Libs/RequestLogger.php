<?php

namespace App\Core\RestApi\Libs;

use App\Core\RestApi\Models\ApiKey;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Exception\CredentialsException;
use DateTime;
use DateTimeZone;
use ICanBoogie\Inflector;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs incoming API requests and the response
 * for debugging purposes.
 */
class RequestLogger implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private const TABLENAME = 'InvoicedApiLogs';

    private const MAX_STRING_SIZE = 102400; // 100KB, max DynamoDB item size is 400KB

    private const IGNORE_ROUTES = [
        'get_latest_user_notification',
    ];

    private const IGNORE_REQUEST_HEADERS = [
        'Accept',
        'Accept-Charset',
        'Accept-Encoding',
        'Accept-Language',
        'Authorization',
        'Charset',
        'Connection',
        'Content-Length',
        'Dnt',
        'Host',
        'Origin',
        'Php-Auth-Pw',
        'Php-Auth-User',
        'Referer',
        'User-Agent',
        'X-Php-Ob-Level',
    ];

    private const IGNORE_RESPONSE_HEADERS = [
        'Access-Control-Allow-Credentials',
        'Access-Control-Allow-Methods',
        'Access-Control-Allow-Origin',
        'Access-Control-Expose-Headers',
        'Access-Control-Max-Age',
        'Cache-Control',
        'Date',
        'Strict-Transport-Security',
        'Www-Authenticate',
        'X-Content-Type-Options',
        'X-Correlation-Id',
        'X-Frame-Options',
        'X-Request-Id',
    ];
    private array $queue = [];

    public function __construct(
        private DynamoDbClient $dynamodb,
        private DebugContext $debugContext,
        private TenantContext $tenant,
    ) {
    }

    /**
     * Logs a request and its corresponding response.
     *
     * @param int|float $responseTime response time in milliseconds
     *
     * @return $this
     */
    public function log(Request $request, Response $response, $responseTime, ?ApiKey $apiKey)
    {
        // if the request is a 404/405 because the route does not exist
        // then we don't want to waste log space logging it
        if ($request->attributes->get('_doNotLogApiRequest')) {
            return $this;
        }

        // log the request on statsd
        $method = strtolower($request->getMethod());
        $statusCode = $response->getStatusCode();
        $endpoint = str_replace('api_', '', (string) $request->attributes->get('_route'));
        // api key is internal only if it is protected
        // if there is no api key then this could be a request with an oauth access token
        $isInternal = $apiKey?->protected;

        $this->statsd->timing('api.response_time', $responseTime, 1.0, [
            'method' => $method,
            'status_code' => $statusCode,
            'endpoint' => $endpoint,
            'internal' => $isInternal ? '1' : '0',
        ]);
        $this->statsd->increment('api.request', 1.0, [
            'method' => $method,
            'endpoint' => $endpoint,
            'internal' => $isInternal ? '1' : '0',
        ]);
        $this->statsd->increment('api.response', 1.0, [
            'status_code' => $statusCode,
            'endpoint' => $endpoint,
            'internal' => $isInternal ? '1' : '0',
        ]);
        $request->attributes->set('responseTime', $responseTime);

        // add the request to the queue to be logged
        // after the request finishes.
        $this->queue[] = [$request, $response, $apiKey, $endpoint];

        return $this;
    }

    /**
     * Flushes the request logging queue.
     *
     * @return $this
     */
    public function flush()
    {
        if (0 == count($this->queue)) {
            return $this;
        }

        $marshaler = new Marshaler();

        foreach ($this->queue as $entry) {
            [$request, $response, $apiKey, $routeName] = $entry;
            if (!$this->shouldLogRoute($routeName)) {
                continue;
            }

            $json = $this->buildLogJson($request, $response, $apiKey, $routeName);
            $params = [
                'TableName' => self::TABLENAME,
                'Item' => $marshaler->marshalJson($json),
            ];

            try {
                $this->dynamodb->putItem($params);
            } catch (CredentialsException $e) {
                if (isset($this->logger)) {
                    $this->logger->error('Could not connect to DynamoDB', ['exception' => $e]);
                }
            } catch (DynamoDbException $e) {
                if (isset($this->logger)) {
                    $this->logger->error('Could not log API request to DynamoDB', ['exception' => $e]);
                }
            }
        }

        $this->queue = [];

        return $this;
    }

    /**
     * Builds a log entry and returns the JSON encoded version.
     */
    public function buildLogJson(Request $request, Response $response, ?ApiKey $apiKey, string $routeName): string
    {
        $id = $this->debugContext->getEnvironment().':'.$this->tenant->get()->id();

        $body = (string) $response->getContent();
        if (strlen($body) > self::MAX_STRING_SIZE) {
            mb_internal_encoding('UTF-8');
            $body = mb_strcut($body, 0, self::MAX_STRING_SIZE);
        }

        // log timestamps must always be in UTC
        $date = new DateTime();
        $date->setTimezone(new DateTimeZone('UTC'));

        $params = [
            'id' => $id,
            'request_id' => $this->debugContext->getRequestId(),
            'correlation_id' => $this->debugContext->getCorrelationId(),
            'timestamp' => $date->format('Y-m-d H:i:s.v'),
            'method' => $request->getMethod(),
            'endpoint' => $request->getPathInfo(),
            'route_name' => $routeName,
            'status_code' => $response->getStatusCode(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'response' => $this->compress($body),
            'expires' => strtotime('+30 days'),
        ];

        if ($responseTime = $request->attributes->get('responseTime')) {
            $params['response_time'] = $responseTime;
        }

        if ($input = $request->getContent()) {
            $params['request_body'] = $this->compress($input);
        }

        if ($queryParams = $request->query->all()) {
            $params['query_params'] = $queryParams;
        }

        if ($headers = $this->getRequestHeaders($request)) {
            $params['request_headers'] = $headers;
        }

        if ($headers = $this->getResponseHeaders($response)) {
            $params['response_headers'] = $headers;
        }

        if ($apiKey) {
            $params['api_key'] = $apiKey->id();
            if ($user = $apiKey->user_id) {
                $params['user'] = $user;
            }
        }

        return (string) json_encode($params);
    }

    /**
     * Gets the scrubbed headers from a request.
     */
    private function getRequestHeaders(Request $request): array
    {
        $result = [];
        $headers = $request->headers->all();
        foreach ($headers as $key => $value) {
            $key = $this->formatHeaderName((string) $key);
            $value = $value[0] ?? null;
            if (!$key || !$value) {
                continue;
            }

            if (!in_array($key, self::IGNORE_REQUEST_HEADERS)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Gets the headers from a response.
     */
    private function getResponseHeaders(Response $response): array
    {
        $result = [];
        $headers = $response->headers->all();
        foreach ($headers as $key => $value) {
            $key = $this->formatHeaderName((string) $key);
            $value = $value[0] ?? null;
            if (!$key || !$value || in_array($key, self::IGNORE_RESPONSE_HEADERS)) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Formats a header from the PHP version to a
     * more readable version, i.e. USER_AGENT -> User-Agent.
     */
    private function formatHeaderName(string $name): string
    {
        // the inflector chops off `_id` at the end
        $hasId = '-id' == substr(strtolower($name), -3, 3);

        $name = Inflector::get()->titleize($name);

        return str_replace(' ', '-', $name).(($hasId) ? '-Id' : '');
    }

    /**
     * Compresses a string.
     */
    private function compress(string $str): string
    {
        return base64_encode((string) gzdeflate($str, 9));
    }

    private function shouldLogRoute(string $routeName): bool
    {
        // Do not log these routes because they are high volume and low value
        return !in_array($routeName, self::IGNORE_ROUTES);
    }
}
