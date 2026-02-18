<?php

namespace App\Tests\ActivityLog;

use App\Companies\Models\Company;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingCreateEvent;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\ActivityLog\ValueObjects\PendingUpdateEvent;
use App\ActivityLog\Models\Event;
use App\AccountsReceivable\Models\Invoice;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;

class EventSpoolTest extends AppTestCase
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        // create a new company
        self::getService('test.tenant')->clear();
        self::$company2 = new Company();
        self::$company2->name = 'Test';
        self::$company2->username = 'test2'.time();
        self::$company2->saveOrFail();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        self::getService('test.tenant')->set(self::$company);
        self::getService('test.event_spool')->clear();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        self::$company2->delete();
    }

    public function testMiddleware(): void
    {
        $this->assertInstanceOf(EventSpool::class, self::getService('test.event_spool'));
    }

    public function testEnqueue(): void
    {
        EventSpool::enable();

        $queue = $this->getEventSpool();

        $pendingEvent = new PendingEvent(self::$invoice, EventType::CustomerCreated);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(1, $queue->size());

        $queue->clear();
        $this->assertEquals(0, $queue->size());
    }

    public function testEnqueueUpdateMerge(): void
    {
        EventSpool::enable();

        $queue = $this->getEventSpool();

        $pendingEvent = new PendingCreateEvent(self::$invoice, EventType::InvoiceCreated);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(1, $queue->size());

        // this should be merged
        $pendingEvent = new PendingUpdateEvent(self::$invoice, EventType::InvoiceUpdated);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(1, $queue->size());

        // this should also be merged and the model reference should be updated
        $pendingEvent = new PendingUpdateEvent(clone self::$invoice, EventType::InvoiceUpdated);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(1, $queue->size());
        $this->assertSame($pendingEvent->getObject(), $queue->peek()?->getObject());

        $pendingEvent = new PendingUpdateEvent(self::$customer, EventType::CustomerUpdated);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(2, $queue->size());

        $invoice = new Invoice();
        $pendingEvent = new PendingUpdateEvent($invoice, EventType::InvoiceUpdated);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(3, $queue->size());

        $queue->clear();

        $pendingEvent = new PendingUpdateEvent(self::$invoice, EventType::InvoiceUpdated, [], [], ['total' => 100]);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(1, $queue->size());

        // this should also be merged
        $pendingEvent = new PendingUpdateEvent(self::$invoice, EventType::InvoiceUpdated, [], [], ['total' => 200, 'subtotal' => 150]);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(1, $queue->size());

        // verify previous attributes
        $queue->flush();
        $event = Event::where('object_type_id', ObjectType::Invoice->value)
            ->where('object_id', self::$invoice)
            ->sort('id DESC')
            ->one();
        $storage = self::getService('test.event_storage');
        $event->hydrateFromStorage($storage);
        $expected = (object) ['total' => 100, 'subtotal' => 150];
        $this->assertEquals($expected, $event->previous);
    }

    public function testEnqueueDeleteMerge(): void
    {
        EventSpool::enable();

        $queue = $this->getEventSpool();

        $pendingEvent = new PendingCreateEvent(self::$invoice, EventType::InvoiceCreated);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(1, $queue->size());

        $pendingEvent = new PendingUpdateEvent(self::$invoice, EventType::InvoicePaid);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(2, $queue->size());

        $pendingEvent = new PendingDeleteEvent(self::$invoice, EventType::InvoiceDeleted);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(0, $queue->size());

        // since there is no previous created event then it should
        // behave normally
        $pendingEvent = new PendingDeleteEvent(self::$invoice, EventType::InvoiceDeleted);
        $queue->enqueue($pendingEvent);
        $this->assertEquals(1, $queue->size());

        $queue->clear();
    }

    public function testClearingCurrentTenantFlushesQueue(): void
    {
        EventSpool::enable();

        $queue = self::getService('test.event_spool');

        $pendingEvent = new PendingCreateEvent(self::$customer, EventType::CustomerCreated);
        $queue->enqueue($pendingEvent);

        // clearing tenant should flush out events
        self::getService('test.tenant')->clear();

        $this->assertEquals(0, $queue->size());
        $this->assertEquals(1, Event::queryWithTenant(self::$company)->where('type_id', EventType::CustomerCreated->toInteger())->count());
    }

    public function testChangingCurrentTenantFlushesQueue(): void
    {
        EventSpool::enable();

        $queue = self::getService('test.event_spool');

        $pendingEvent = new PendingCreateEvent(self::$customer, EventType::CustomerUpdated);
        $queue->enqueue($pendingEvent);

        // switching tenant should flush out events
        self::getService('test.tenant')->set(self::$company2);

        $this->assertEquals(0, $queue->size());
        $this->assertEquals(1, Event::queryWithTenant(self::$company)->where('type_id', EventType::CustomerUpdated->toInteger())->count());
    }

    public function testFlush(): void
    {
        EventSpool::enable();

        $queue = $this->getEventSpool();
        $pendingEvent = new PendingCreateEvent(self::$customer, EventType::CustomerMerged);
        $queue->enqueue($pendingEvent);

        $queue->flush();

        $this->assertEquals(1, Event::where('type_id', EventType::CustomerMerged->toInteger())->count());
    }

    public function testEventFlushInsideTransaction(): void
    {
        EventSpool::enable();

        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $params = $connection->getParams();
        $driver = $connection->getDriver();
        $connection2 = new Connection($params, $driver);

        $count = $connection2->fetchOne('SELECT COUNT(*) FROM Events');

        $connection->beginTransaction();

        $associations = [
            ['customer', self::$customer->id()],
            ['estimate', 1],
            ['invoice', 2],
            ['should_fail', 0],
        ];

        $queue = $this->getEventSpool();
        for ($i = 0; $i < 100; ++$i) {
            $queue->enqueue(new PendingEvent(self::$customer, EventType::CustomerCreated));
        }

        $this->assertEquals(100, $queue->size());
        $result = $connection2->fetchOne('SELECT COUNT(*) FROM Events');
        $this->assertEquals($count, $result);

        $connection->commit();

        $result = $connection2->fetchOne('SELECT COUNT(*) FROM Events');
        $this->assertEquals($count, $result);

        $queue->enqueue(new PendingUpdateEvent(self::$customer, EventType::CustomerUpdated, ['test' => true], $associations, ['test' => false]));
        $this->assertEquals(0, $queue->size());
        $result = $connection2->fetchOne('SELECT COUNT(*) FROM Events');
        $this->assertEquals($count + 101, $result);
    }

    public function testDestructor(): void
    {
        EventSpool::enable();

        $queue = new EventSpool(self::getService('test.event_writer'));
        $pendingEvent = new PendingDeleteEvent(self::$customer, EventType::CustomerDeleted);
        $queue->enqueue($pendingEvent);
        $queue->enqueue($pendingEvent);

        // test destruction even in case DB transaction is running
        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $connection->beginTransaction();
        // destroying the object should flush out events
        $queue = null;

        $this->assertEquals(2, Event::where('type_id', EventType::CustomerDeleted->toInteger())->count());

        $connection->commit();
    }

    private function getEventSpool(): EventSpool
    {
        return self::getService('test.event_spool');
    }
}
