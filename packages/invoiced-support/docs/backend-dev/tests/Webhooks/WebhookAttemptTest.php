<?php

namespace App\Tests\Webhooks;

use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\WebhookJob;
use App\Tests\AppTestCase;
use App\Webhooks\Models\WebhookAttempt;
use Mockery;

class WebhookAttemptTest extends AppTestCase
{
    private static WebhookAttempt $attempt;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$attempt = new WebhookAttempt();
        self::$attempt->event_id = -1;
        self::$attempt->url = 'https://example.com/webhook';
        $this->assertTrue(self::$attempt->save());
        $this->assertEquals(self::$company->id(), self::$attempt->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$attempt->attempts = [
            [
                'status_code' => 200,
                'timestamp' => 100000,
            ],
        ];

        $this->assertTrue(self::$attempt->save());
    }

    public function testToArray(): void
    {
        $expected = [
            'id' => self::$attempt->id(),
            'attempts' => [
                [
                    'status_code' => 200,
                    'timestamp' => 100000,
                ],
            ],
            'event_id' => -1,
            'url' => 'https://example.com/webhook',
            'next_attempt' => null,
            'created_at' => self::$attempt->created_at,
            'updated_at' => self::$attempt->updated_at,
        ];

        $this->assertEquals($expected, self::$attempt->toArray());
    }

    public function testSucceeded(): void
    {
        $attempt = new WebhookAttempt();
        $attempt->attempts = [];
        $this->assertFalse($attempt->succeeded());

        $attempts = [['status_code' => 300]];
        $attempt->attempts = $attempts;
        $this->assertFalse($attempt->succeeded());

        $attempts[] = ['error' => 'Could not connect'];
        $attempt->attempts = $attempts;
        $this->assertFalse($attempt->succeeded());

        $attempts[] = ['status_code' => 200];
        $attempt->attempts = $attempts;
        $this->assertTrue($attempt->succeeded());
    }

    public function testQueue(): void
    {
        $attempt = new WebhookAttempt(['id' => 100]);

        // mock queueing operations
        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')
            ->andReturnUsing(function ($class, $args) {
                $this->assertEquals(WebhookJob::class, $class);
                $this->assertEquals(100, $args['id']);
            })
            ->once();

        $attempt->queue($queue);
    }
}
