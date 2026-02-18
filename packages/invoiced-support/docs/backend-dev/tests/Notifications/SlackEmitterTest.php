<?php

namespace App\Tests\Notifications;

use App\Core\Utils\DebugContext;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Slack\SlackAccount;
use App\Notifications\Emitters\SlackEmitter;
use App\Tests\AppTestCase;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery;

class SlackEmitterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasSlackAccount();
    }

    private function getEmitter(): SlackEmitter
    {
        return new SlackEmitter(Mockery::mock(CloudWatchLogsClient::class), new DebugContext('test'), new IntegrationFactory());
    }

    public function testEmit(): void
    {
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = EventType::InvoiceViewed->value;
        $event->object = (object) [];
        $responses = [
            new Response(200),
        ];
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);
        $emitter = $this->getEmitter();
        $emitter->setClient($client);
        $this->assertTrue($emitter->emit($event));
    }

    public function testEmitFail(): void
    {
        $responses = [
            new Response(404),
        ];
        $mock = new MockHandler($responses);
        $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'headers' => ['User-Agent' => 'Invoiced/1.0'],
        ]);

        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = EventType::InvoiceViewed->value;
        $event->object = (object) [];
        $emitter = $this->getEmitter();
        $emitter->setClient($client);
        $emitter->emit($event);

        $this->assertEquals(0, SlackAccount::queryWithCurrentTenant()->count());
    }

    public function testGetColor(): void
    {
        $emitter = $this->getEmitter();
        $this->assertNull($emitter->getColor('invoice.created'));
        $this->assertEquals('#4B94D9', $emitter->getColor('
			invoice.viewed'));
        $this->assertEquals('#54BF83', $emitter->getColor('invoice.paid'));
        $this->assertEquals('#C14543', $emitter->getColor('invoice.deleted'));
    }

    public function testBuildMessage(): void
    {
        $emitter = $this->getEmitter();

        $event = Mockery::mock(Event::class);
        $event->shouldReceive('get')
            ->withArgs([['type']])
            ->andReturn([EventType::InvoiceViewed->value]);
        $event->shouldReceive('get')
            ->withArgs([['href']])
            ->andReturn(['http://example.com']);
        $event->shouldReceive('getTitle')
            ->andReturn('Invoice viewed');
        $event->shouldReceive('getMessage->toString')
            ->andReturn('Customer viewed Invoice INV-0001');

        $expected = [
            'attachments' => [
                [
                    'title' => '<http://example.com|Invoice viewed>',
                    'text' => 'Customer viewed Invoice INV-0001',
                    'color' => '#4B94D9',
                ],
            ],
        ];

        $this->assertEquals($expected, $emitter->buildMessage($event));
    }
}
