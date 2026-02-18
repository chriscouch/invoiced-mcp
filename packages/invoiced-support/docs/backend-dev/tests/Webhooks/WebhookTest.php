<?php

namespace App\Tests\Webhooks;

use App\AccountsReceivable\Models\Customer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Tests\AppTestCase;
use App\Webhooks\EventSubscriber\WebhookSubscriber;
use App\Webhooks\Models\Webhook;
use App\Webhooks\Models\WebhookAttempt;
use App\Webhooks\Storage\NullStorage;

class WebhookTest extends AppTestCase
{
    private static Webhook $webhook;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testGetSupportedEvents(): void
    {
        $subscriber = $this->getSubscriber();

        $webhook = new Webhook();
        $supported = [
            'charge.failed',
            'contact.created',
            'contact.updated',
            'contact.deleted',
            'credit_note.created',
            'credit_note.updated',
            'credit_note.deleted',
            'credit_note.viewed',
            'credit_note.commented',
            'customer.created',
            'customer.updated',
            'customer.deleted',
            'customer.merged',
            'estimate.created',
            'estimate.updated',
            'estimate.deleted',
            'estimate.viewed',
            'estimate.approved',
            'estimate.commented',
            'invoice.created',
            'invoice.updated',
            'invoice.deleted',
            'invoice.paid',
            'invoice.viewed',
            'invoice.commented',
            'line_item.created',
            'line_item.updated',
            'line_item.deleted',
            'note.created',
            'note.updated',
            'note.deleted',
            'payment.created',
            'payment.updated',
            'payment.deleted',
            'payment_plan.created',
            'payment_plan.updated',
            'payment_plan.deleted',
            'payment_source.created',
            'payment_source.updated',
            'payment_source.deleted',
            'refund.created',
            'subscription.created',
            'subscription.updated',
            'subscription.deleted',
            'task.created',
            'task.updated',
            'task.deleted',
            'task.completed',
            'transaction.created',
            'transaction.updated',
            'transaction.deleted',
        ];
        foreach ($supported as $event) {
            $this->assertTrue($subscriber->isEventSupported($webhook, $event));
        }

        $webhook->events = ['invoice.created'];
        $this->assertTrue($subscriber->isEventSupported($webhook, 'invoice.created'));
        $this->assertFalse($subscriber->isEventSupported($webhook, 'invoice.updated'));
        $this->assertFalse($subscriber->isEventSupported($webhook, 'customer.created'));

        $webhook->events = [];
        $this->assertFalse($subscriber->isEventSupported($webhook, 'invoice.created'));
    }

    public function testCreateInvalidURL(): void
    {
        self::$webhook = new Webhook();
        self::$webhook->url = 'not a url';
        $this->assertFalse(self::$webhook->save());
    }

    public function testCreate(): void
    {
        self::$webhook = new Webhook();
        self::$webhook->url = 'https://example.com/webhook';
        $this->assertTrue(self::$webhook->save());
        $this->assertEquals(self::$company->id(), self::$webhook->tenant_id);

        $this->assertEquals(32, strlen(self::$webhook->secret));
        $this->assertEquals(self::$webhook->secret, self::$webhook->secret_enc);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$webhook->enabled = false;
        $this->assertTrue(self::$webhook->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$webhook->id(),
            'url' => 'https://example.com/webhook',
            'enabled' => false,
            'events' => ['*'],
            'created_at' => self::$webhook->created_at,
            'updated_at' => self::$webhook->updated_at,
        ];

        $this->assertEquals($expected, self::$webhook->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testGetSecret(): void
    {
        $secret = self::$webhook->getSecret();
        $this->assertEquals($secret, self::$webhook->secret);
        $this->assertEquals($secret, self::$webhook->secret_enc);

        for ($i = 0; $i < 5; ++$i) {
            $this->assertEquals($secret, self::$webhook->getSecret());
            $this->assertEquals($secret, self::$webhook->secret);
        }
    }

    /**
     * @depends testCreate
     */
    public function testGetSecretRolling(): void
    {
        $secret = self::$webhook->getSecret();
        $secret2 = self::$webhook->getSecret(true);

        $this->assertNotEquals($secret, $secret2);
        $this->assertEquals($secret2, self::$webhook->secret);
    }

    public function testEmitEventNotSupported(): void
    {
        $subscriber = $this->getSubscriber();

        $webhook = new Webhook();
        $webhook->events = ['customer.created'];

        $event = new Event();
        $event->type = EventType::EmailSent->value;
        $this->assertFalse($subscriber->emit($webhook, $event));
    }

    public function testEmit(): void
    {
        $subscriber = $this->getSubscriber();

        // create a webhook
        $webhook = new Webhook();
        $webhook->url = 'https://example.com/webhook';

        // create an event
        $event = new Event(['id' => 100]);
        $event->type = EventType::CustomerCreated->value;
        $event->object = (object) [
            'id' => 123,
            'name' => 'Customer',
        ];

        // and emit it
        $this->assertTrue($subscriber->emit($webhook, $event));

        // should create a webhook attempt
        $attempt = WebhookAttempt::where('url', 'https://example.com/webhook')
            ->where('event_id', 100)
            ->oneOrNull();

        $this->assertInstanceOf(WebhookAttempt::class, $attempt);
        $this->assertGreaterThan(time(), $attempt->next_attempt);
    }

    /**
     * @depends testCreate
     */
    public function testEmitFromEvent(): void
    {
        // enable the webhook
        self::$webhook->enabled = true;
        $this->assertTrue(self::$webhook->save());

        // record an event
        $customer = new Customer(['id' => 123]);
        $customer->tenant_id = (int) self::$company->id();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated);
        $writer = self::getService('test.event_writer');
        $writer->write([$pendingEvent]);

        // should create a webhook attempt
        $event = Event::where('type_id', EventType::CustomerCreated->toInteger())->oneOrNull();
        $attempt = WebhookAttempt::where('url', 'https://example.com/webhook')
            ->where('event_id', $event)
            ->oneOrNull();

        $this->assertInstanceOf(WebhookAttempt::class, $attempt);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$webhook->delete());
    }

    private function getSubscriber(): WebhookSubscriber
    {
        return new WebhookSubscriber(self::getService('test.queue'), new NullStorage());
    }
}
