<?php

namespace App\Tests\Webhooks;

use App\Core\Statsd\StatsdClient;
use App\Tests\AppTestCase;
use App\Webhooks\Pusher;
use App\Webhooks\Models\Webhook;
use App\Webhooks\Storage\NullStorage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class PusherTest extends AppTestCase
{
    private static Webhook $webhook;
    private static string $secret = '4b37d5776dad4059ab4c80fa072e3fce';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::$webhook = new Webhook();
        self::$webhook->url = 'https://example.com/webhook';
        self::$webhook->saveOrFail();
    }

    private function getPusher(?Client $client = null): Pusher
    {
        $pusher = new Pusher(new NullStorage(), self::getService('test.mailer'), $client);
        $pusher->setStatsd(new StatsdClient());

        return $pusher;
    }

    public function testClient(): void
    {
        $pusher = $this->getPusher();
        $client = $pusher->getClient();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testGetHeaders(): void
    {
        $pusher = $this->getPusher();

        $payload = '{"test":true}';

        $expected = [
            'User-Agent' => 'Invoiced/1.0',
            'Content-Type' => 'application/json',
            'X-Invoiced-Signature' => '341cb5ca9555e923c908c2eb5d36189ea11d90c0dfd559377c5edb669f9cf240',
        ];

        $this->assertEquals($expected, $pusher->getHeaders($payload, self::$secret));
    }

    public function testSignRequest(): void
    {
        $pusher = $this->getPusher();

        $payload = '{"test":true}';
        $signature = '341cb5ca9555e923c908c2eb5d36189ea11d90c0dfd559377c5edb669f9cf240';
        $this->assertEquals($signature, $pusher->signRequest($payload, self::$secret));
    }

    public function testCall(): void
    {
        $mock = new MockHandler([
            new Response(200),
            new Response(301),
            new Response(404),
            new Response(500),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $pusher = $this->getPusher($client);

        $response1 = $pusher->call('https://example.com/webhook', '{"payload":true}', self::$secret);
        $this->assertInstanceOf(ResponseInterface::class, $response1);
        $this->assertEquals(200, $response1->getStatusCode());

        $response2 = $pusher->call('https://example.com/webhook', '{"payload":true}', self::$secret);
        $this->assertEquals(301, $response2->getStatusCode());

        $response3 = $pusher->call('https://example.com/webhook', '{"payload":true}', self::$secret);
        $this->assertEquals(404, $response3->getStatusCode());

        $response4 = $pusher->call('https://example.com/webhook', '{"payload":true}', self::$secret);
        $this->assertEquals(500, $response4->getStatusCode());
    }

    public function testPerformAttempt(): void
    {
        $mock = new MockHandler([
            new Response(200),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $pusher = $this->getPusher($client);

        $payload = new stdClass();
        $payload->id = 1234;

        $attempt = Mockery::mock('App\Webhooks\Models\WebhookAttempt[save]');
        $attempt->tenant_id = (int) self::$company->id();
        $attempt->url = 'https://example.com/webhook';
        $attempt->payload = (string) json_encode($payload);

        $attempt->shouldReceive('save');

        $this->assertTrue($pusher->performAttempt($attempt));

        // should record the successful attempt
        $this->assertTrue($attempt->succeeded());
        $this->assertCount(1, $attempt->attempts);
        $this->assertEquals(200, $attempt->attempts[0]['status_code']);
        $this->assertLessThan(3, abs(time() - $attempt->attempts[0]['timestamp']));
    }

    public function testFailedAttempt(): void
    {
        $mock = new MockHandler([
            new Response(500),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $pusher = $this->getPusher($client);

        $payload = new stdClass();
        $payload->id = 1234;

        $attempt = Mockery::mock('App\Webhooks\Models\WebhookAttempt[save]');
        $attempt->tenant_id = (int) self::$company->id();
        $attempt->url = 'https://example.com/webhook';
        $attempt->payload = (string) json_encode($payload);

        $attempt->shouldReceive('save');

        // perform a failed attempt
        $this->assertFalse($pusher->performAttempt($attempt));

        // should record the failed attempt
        $this->assertCount(1, $attempt->attempts);
        $this->assertEquals(500, $attempt->attempts[0]['status_code']);
        $this->assertLessThan(3, abs(time() - $attempt->attempts[0]['timestamp']));

        // should schedule a retry
        $this->assertGreaterThan(time() + 3599, $attempt->next_attempt);
    }

    public function testErroredAttempt(): void
    {
        $request = new Request('GET', 'https://api.invoiced.com');
        $mock = new MockHandler([
            new RequestException('Could not connect', $request),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $pusher = $this->getPusher($client);

        $payload = new stdClass();
        $payload->id = 1234;

        $attempt = Mockery::mock('App\Webhooks\Models\WebhookAttempt[save]');
        $attempt->tenant_id = (int) self::$company->id();
        $attempt->url = 'https://example.com/webhook';
        $attempt->payload = (string) json_encode($payload);
        $attempt->attempts = [['status_code' => 500]];

        $attempt->shouldReceive('save');

        // perform a failed attempt
        $this->assertFalse($pusher->performAttempt($attempt));

        // should record the failed attempt
        $this->assertCount(2, $attempt->attempts);
        $this->assertEquals(['status_code' => 500], $attempt->attempts[0]);
        $this->assertEquals('Could not connect', $attempt->attempts[1]['error']); /* @phpstan-ignore-line */
        $this->assertLessThan(3, abs(time() - $attempt->attempts[1]['timestamp'])); /* @phpstan-ignore-line */

        // should schedule a retry
        $this->assertGreaterThan(time() + 3599, $attempt->next_attempt);
    }

    public function testFinalFailedAttempt(): void
    {
        $mock = new MockHandler([
            new Response(404),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $pusher = $this->getPusher($client);

        $payload = new stdClass();
        $payload->id = 1234;

        $attempt = Mockery::mock('App\Webhooks\Models\WebhookAttempt[save]');
        $attempt->tenant_id = (int) self::$company->id();
        $attempt->url = 'https://example.com/webhook';
        $attempt->payload = (string) json_encode($payload);
        $attempt->attempts = array_fill(0, 47, ['status_code' => 300]);

        $attempt->shouldReceive('save');

        // perform a failed attempt
        $this->assertFalse($pusher->performAttempt($attempt));

        // should record the failed attempt
        $this->assertCount(48, $attempt->attempts);
        $this->assertEquals(404, $attempt->attempts[47]['status_code']); /* @phpstan-ignore-line */
        $this->assertLessThan(3, abs(time() - $attempt->attempts[47]['timestamp'])); /* @phpstan-ignore-line */

        // should disable the webhook
        $this->assertFalse(self::$webhook->refresh()->enabled);
    }

    public function testPerformAttemptRetrySucceeds(): void
    {
        $mock = new MockHandler([
            new Response(200),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $pusher = $this->getPusher($client);

        $payload = new stdClass();
        $payload->id = 1234;

        $attempt = Mockery::mock('App\Webhooks\Models\WebhookAttempt[save]');
        $attempt->tenant_id = (int) self::$company->id();
        $attempt->url = 'https://example.com/webhook';
        $attempt->payload = (string) json_encode($payload);
        $attempt->next_attempt = time();
        $attempt->attempts = [['status_code' => 500]];

        $attempt->shouldReceive('save');

        $this->assertTrue($pusher->performAttempt($attempt));

        // should record the successful attempt
        $this->assertTrue($attempt->succeeded());
        $this->assertCount(2, $attempt->attempts);
        $this->assertEquals(200, $attempt->attempts[1]['status_code']); /* @phpstan-ignore-line */
        $this->assertLessThan(3, abs(time() - $attempt->attempts[1]['timestamp'])); /* @phpstan-ignore-line */
        $this->assertNull($attempt->next_attempt);
    }

    public function testPerformAttemptReplayAlreadySucceeded(): void
    {
        $pusher = $this->getPusher();

        $payload = new stdClass();
        $payload->id = 1234;

        $attempt = Mockery::mock('App\Webhooks\Models\WebhookAttempt[save]');
        $attempt->tenant_id = (int) self::$company->id();
        $attempt->url = 'https://example.com/webhook';
        $attempt->payload = (string) json_encode($payload);
        $attempt->next_attempt = time();
        $attempt->attempts = [['status_code' => 200]];

        $attempt->shouldReceive('save');

        $this->assertTrue($pusher->performAttempt($attempt));

        // should be able to replay webhooks
        $this->assertTrue($attempt->succeeded());
        $this->assertNull($attempt->next_attempt);
    }

    public function testPerformAttempt410Response(): void
    {
        self::$webhook->enabled = true;
        $this->assertTrue(self::$webhook->save());

        $mock = new MockHandler([
            new Response(410),
        ]);

        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $pusher = $this->getPusher($client);

        $payload = new stdClass();
        $payload->id = 1234;

        $attempt = Mockery::mock('App\Webhooks\Models\WebhookAttempt[save]');
        $attempt->tenant_id = (int) self::$company->id();
        $attempt->url = 'https://example.com/webhook';
        $attempt->payload = (string) json_encode($payload);
        $attempt->attempts = [];

        $attempt->shouldReceive('save');

        // perform a failed attempt
        $this->assertFalse($pusher->performAttempt($attempt));

        // should record the failed attempt
        $this->assertCount(1, $attempt->attempts);
        $this->assertEquals(410, $attempt->attempts[0]['status_code']); /* @phpstan-ignore-line */
        $this->assertLessThan(3, abs(time() - $attempt->attempts[0]['timestamp'])); /* @phpstan-ignore-line */

        // should disable the webhook
        $this->assertFalse(self::$webhook->refresh()->enabled);
    }
}
