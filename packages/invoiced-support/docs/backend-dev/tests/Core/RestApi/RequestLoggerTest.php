<?php

namespace App\Tests\Core\RestApi;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Libs\RequestLogger;
use App\Core\RestApi\Models\ApiKey;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\DebugContext;
use App\Tests\AppTestCase;
use Aws\DynamoDb\DynamoDbClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RequestLoggerTest extends AppTestCase
{
    private function getLogger(): RequestLogger
    {
        $statsd = \Mockery::mock(StatsdClient::class);
        $statsd->shouldReceive('increment');
        $statsd->shouldReceive('timing');
        $dynamodb = \Mockery::mock(DynamoDbClient::class);
        $dynamodb->shouldReceive('putItem');
        $tenant = new TenantContext(self::getService('test.event_spool'), self::getService('test.email_spool'));
        $tenant->set(new Company(['id' => 1]));
        $debugContext = new DebugContext('test');
        $debugContext->setRequestId('1234');
        $debugContext->setCorrelationId('456');
        $logger = new RequestLogger($dynamodb, $debugContext, $tenant);
        $logger->setStatsd($statsd);

        return $logger;
    }

    private function getRequest(): Request
    {
        $request = Request::create('/invoices', 'GET', ['sort' => 'date asc', 'filter' => ['customer' => '']], [], [], ['HTTP_USER_AGENT' => 'Invoiced/1.0', 'HTTP_AUTHORIZATION' => 'SECRET', 'HTTP_INVOICED_VERSION' => '2.0']);
        $request->attributes->replace([
            'responseTime' => 100,
        ]);

        return $request;
    }

    private function getResponse(): JsonResponse
    {
        return new JsonResponse(['test' => true], 200, [
            'X-Request-Id' => '1234',
            'X-Correlation-Id' => '4567',
        ]);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testLogAndFlush(): void
    {
        $logger = $this->getLogger();
        $logger->log($this->getRequest(), $this->getResponse(), 200, null);
        $logger->flush();
    }

    public function testBuildLogJson(): void
    {
        $logger = $this->getLogger();
        $apiKey = new ApiKey(['id' => 1, 'user_id' => 2]);
        $params = $logger->buildLogJson($this->getRequest(), $this->getResponse(), $apiKey, 'list_invoices');
        $params = json_decode($params, true);

        $expected = [
            'id' => 'test:1',
            'request_id' => '1234',
            'correlation_id' => '456',
            'timestamp' => $params['timestamp'],
            'method' => 'GET',
            'endpoint' => '/invoices',
            'route_name' => 'list_invoices',
            'query_params' => [
                'sort' => 'date asc',
                'filter' => ['customer' => ''],
            ],
            'status_code' => 200,
            'ip' => '127.0.0.1',
            'user_agent' => 'Invoiced/1.0',
            'response' => 'q1YqSS0uUbIqKSpNrQUA',
            'response_time' => 100,
            'api_key' => 1,
            'user' => 2,
            'request_headers' => [
                'Invoiced-Version' => '2.0',
            ],
            'response_headers' => [
                'Content-Type' => 'application/json',
            ],
            'expires' => $params['expires'],
        ];

        $this->assertEquals($expected, $params);
    }
}
