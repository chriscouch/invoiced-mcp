<?php

namespace App\Tests\ActivityLog;

use App\AccountsReceivable\Models\Comment;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\DocumentView;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Core\Authentication\Models\User;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Libs\Messages\CustomerMessage;
use App\ActivityLog\Libs\Messages\DefaultMessage;
use App\ActivityLog\Models\Event;
use App\Imports\Models\Import;
use App\Sending\Email\Models\Email;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;
use Exception;

class EventTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testGetObjectType(): void
    {
        $event = new Event();
        $this->assertNull($event->object_type);

        $event->type = 'text_message.sent';
        $this->assertEquals('text_message', $event->object_type);

        $event->object_type = 'customer';
        $this->assertEquals('customer', $event->object_type);

        $event = new Event();
        $event->object_type_id = ObjectType::Customer->value;
        $this->assertEquals('customer', $event->object_type);
    }

    public function testGetMessage(): void
    {
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = 'test';
        $event->object_type_id = ObjectType::Customer->value;
        $event->object = (object) ['test' => 'object'];
        $event->previous = (object) ['test' => 'previous'];

        $message = $event->getMessage();
        $this->assertInstanceOf(CustomerMessage::class, $message);
        $this->assertEquals('test', $message->getEventType());
        $this->assertEquals(['test' => 'object'], $message->getObject());
        $this->assertEquals([], $message->getAssociations());
        $this->assertEquals(['test' => 'previous'], $message->getPrevious());

        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = 'test';
        $event->object_type_id = ObjectType::ChasingCadence->value;
        $event->object = (object) [];
        $message = $event->getMessage();
        $this->assertInstanceOf(DefaultMessage::class, $message);
    }

    public function testHref(): void
    {
        $base = self::getParameter('app.dashboard_url');

        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->object_id = 1;

        $event->object_type_id = ObjectType::Customer->value;
        $event->type = EventType::CustomerCreated->value;
        $this->assertEquals($base.'/customers/1?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::Estimate->value;
        $event->type = EventType::EstimateCreated->value;
        $this->assertEquals($base.'/estimates/1?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::Invoice->value;
        $event->type = EventType::InvoiceCreated->value;
        $this->assertEquals($base.'/invoices/1?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::CreditNote->value;
        $event->type = EventType::CreditNoteCreated->value;
        $this->assertEquals($base.'/credit_notes/1?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::Subscription->value;
        $event->type = EventType::SubscriptionCreated->value;
        $this->assertEquals($base.'/subscriptions/1?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::Payment->value;
        $event->type = EventType::PaymentCreated->value;
        $this->assertEquals($base.'/payments/1?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::Transaction->value;
        $event->type = EventType::TransactionCreated->value;
        $this->assertEquals($base.'/transactions/1?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::Import->value;
        $event->type = EventType::ImportFinished->value;
        $this->assertEquals($base.'/imports/1?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::LegacyEmail->value;
        $event->type = EventType::EmailSent->value;
        $this->assertNull($event->href);

        $event->setAssociations(['invoice' => 2, 'estimate' => 1]);
        $event->object_type_id = ObjectType::Comment->value;
        $event->type = EventType::EstimateCommented->value;
        $this->assertEquals($base.'/estimates/1/conversation?account='.self::$company->id(), $event->href);

        $event->object_type_id = ObjectType::Comment->value;
        $event->type = EventType::InvoiceCommented->value;
        $this->assertEquals($base.'/invoices/2/conversation?account='.self::$company->id(), $event->href);
    }

    public function testObject(): void
    {
        $event = new Event();
        $event->object_id = 1;

        $event->object_type_id = ObjectType::Customer->value;
        $object = $event->object();
        $this->assertInstanceOf(Customer::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::Estimate->value;
        $object = $event->object();
        $this->assertInstanceOf(Estimate::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::Invoice->value;
        $object = $event->object();
        $this->assertInstanceOf(Invoice::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::CreditNote->value;
        $object = $event->object();
        $this->assertInstanceOf(CreditNote::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::Subscription->value;
        $object = $event->object();
        $this->assertInstanceOf(Subscription::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::Transaction->value;
        $object = $event->object();
        $this->assertInstanceOf(Transaction::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::LegacyEmail->value;
        $object = $event->object();
        $this->assertInstanceOf(Email::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::Comment->value;
        $object = $event->object();
        $this->assertInstanceOf(Comment::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::DocumentView->value;
        $object = $event->object();
        $this->assertInstanceOf(DocumentView::class, $object);
        $this->assertEquals(1, $object->id());

        $event->object_type_id = ObjectType::Import->value;
        $object = $event->object();
        $this->assertInstanceOf(Import::class, $object);
        $this->assertEquals(1, $object->id());
    }

    public function testGetTypeName(): void
    {
        $event = new Event();
        $event->type = EventType::InvoiceViewed->value;
        $this->assertEquals('Invoice viewed', $event->getTitle());
    }

    public function testUserProperty(): void
    {
        $event = new Event();
        $event->user_id = -2;
        $user = $event->user;
        $expectedUser = new User(['id' => -2]);
        $this->assertEquals($expectedUser->toArray(), $user);
    }

    public function testUserPropertyNotRegistered(): void
    {
        $event = new Event();
        $event->user_id = (int) self::getService('test.user_context')->get()->id();
        $user = $event->user;
        $expected = self::getService('test.user_context')->get()->toArray();
        unset($expected['updated_at']);
        unset($user['updated_at']);
        $this->assertEquals($expected, $user);
    }

    public function testEventDisable(): void
    {
        EventSpool::enable();
        $this->assertFalse(EventSpool::recordingDisabled());

        EventSpool::disable();
        $this->assertTrue(EventSpool::recordingDisabled());
    }

    public function testCreate(): void
    {
        $this->expectException(Exception::class);

        // it should not be possible to create events through the model
        $event = new Event();
        $event->create();
    }

    public function testEdit(): void
    {
        $this->expectException(Exception::class);

        // it should not be possible to modify events
        $event = new Event(['id' => 1]);
        $event->set([
            'type' => EventType::ImportFinished->value,
            'object' => ['count' => 10],
        ]);
    }

    public function testDelete(): void
    {
        $this->expectException(Exception::class);

        // it should not be possible to delete events
        $event = new Event(['id' => 1]);
        $event->delete();
    }

    public function testToArray(): void
    {
        $event = new Event([
            'id' => 1,
            'type' => EventType::CustomerUpdated->value,
            'timestamp' => 2,
            'object' => (object) ['test' => 'object', 'metadata' => (object) []],
            'previous' => (object) ['test' => 'previous'],
        ]);

        $expected = [
            'id' => 1,
            'type' => EventType::CustomerUpdated->value,
            'timestamp' => 2,
            'data' => [
                'object' => (object) ['test' => 'object', 'metadata' => (object) []],
                'previous' => (object) ['test' => 'previous'],
            ],
        ];

        $this->assertEquals($expected, $event->toArray());

        $event = new Event(['id' => 100]);
        $event->type = 'test.created';
        $event->timestamp = 1234;
        $event->object = (object) [
            'test' => true,
            'metadata' => (object) [],
        ];

        $expected = [
            'id' => 100,
            'type' => 'test.created',
            'timestamp' => 1234,
            'data' => [
                'object' => (object) [
                    'test' => true,
                    'metadata' => (object) [],
                ],
            ],
        ];

        $this->assertEquals($expected, $event->toArray());
    }
}
