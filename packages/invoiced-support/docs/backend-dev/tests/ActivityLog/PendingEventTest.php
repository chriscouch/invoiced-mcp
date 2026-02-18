<?php

namespace App\Tests\ActivityLog;

use App\AccountsReceivable\Models\Customer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\ValueObjects\PendingCreateEvent;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\ActivityLog\ValueObjects\PendingUpdateEvent;
use App\Tests\AppTestCase;

class PendingEventTest extends AppTestCase
{
    public function testGetObject(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated);
        $this->assertEquals($customer, $pendingEvent->getObject());
    }

    public function testGetType(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated);
        $this->assertEquals(EventType::CustomerCreated, $pendingEvent->getType());
    }

    public function testMergeType(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated);
        $pendingEvent->mergeType(EventType::CustomerUpdated);
        $this->assertEquals(EventType::CustomerCreated, $pendingEvent->getType());

        $pendingEvent = new PendingEvent($customer, EventType::InvoiceCreated);
        $pendingEvent->mergeType(EventType::InvoiceUpdated);
        $this->assertEquals(EventType::InvoiceCreated, $pendingEvent->getType());

        $pendingEvent = new PendingEvent($customer, EventType::InvoiceUpdated);
        $pendingEvent->mergeType(EventType::InvoicePaid);
        $pendingEvent->mergeType(EventType::InvoiceUpdated);
        $this->assertEquals(EventType::InvoicePaid, $pendingEvent->getType());
    }

    public function testGetMetadata(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated, ['test' => true]);
        $this->assertEquals(['test' => true], $pendingEvent->getExtraObjectData());
    }

    public function testGetAssociations(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated, [], ['customer' => 1234]);
        $this->assertEquals(['customer' => 1234], $pendingEvent->getAssociations());
    }

    public function testGetPrevious(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated, [], [], ['test' => true]);
        $this->assertEquals(['test' => true], $pendingEvent->getPrevious());
    }

    public function testMergePrevious(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated, [], [], ['test' => true]);
        $pendingEvent->mergePrevious(['test' => false, 'hey' => 'works']);
        $this->assertEquals(['test' => true, 'hey' => 'works'], $pendingEvent->getPrevious());
    }

    public function testGetParameters(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::CustomerCreated, [], [], [], ['test' => true]);
        $this->assertEquals(['test' => true], $pendingEvent->getParameters());
    }

    public function testIsCreate(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::InvoiceViewed);
        $createEvent = new PendingCreateEvent($customer, EventType::CustomerCreated);
        $updateEvent = new PendingUpdateEvent($customer, EventType::CustomerUpdated);
        $deleteEvent = new PendingDeleteEvent($customer, EventType::CustomerDeleted);

        $this->assertFalse($pendingEvent->isCreate());
        $this->assertTrue($createEvent->isCreate());
        $this->assertFalse($updateEvent->isCreate());
        $this->assertFalse($deleteEvent->isCreate());
    }

    public function testIsUpdate(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::InvoiceViewed);
        $createEvent = new PendingCreateEvent($customer, EventType::CustomerCreated);
        $updateEvent = new PendingUpdateEvent($customer, EventType::CustomerUpdated);
        $deleteEvent = new PendingDeleteEvent($customer, EventType::CustomerDeleted);

        $this->assertFalse($pendingEvent->isUpdate());
        $this->assertFalse($createEvent->isUpdate());
        $this->assertTrue($updateEvent->isUpdate());
        $this->assertFalse($deleteEvent->isUpdate());
    }

    public function testIsDelete(): void
    {
        $customer = new Customer();
        $pendingEvent = new PendingEvent($customer, EventType::InvoiceViewed);
        $createEvent = new PendingCreateEvent($customer, EventType::CustomerCreated);
        $updateEvent = new PendingUpdateEvent($customer, EventType::CustomerUpdated);
        $deleteEvent = new PendingDeleteEvent($customer, EventType::CustomerDeleted);

        $this->assertFalse($pendingEvent->isDelete());
        $this->assertFalse($createEvent->isDelete());
        $this->assertFalse($updateEvent->isDelete());
        $this->assertTrue($deleteEvent->isDelete());
    }
}
