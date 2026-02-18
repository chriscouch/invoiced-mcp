<?php

namespace App\Tests\Chasing\Models;

use App\CashApplication\Models\Payment;
use App\Chasing\Models\PromiseToPay;
use App\Core\Utils\ModelNormalizer;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class PromiseToPayTest extends AppTestCase
{
    private static PromiseToPay $promiseToPay;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$promiseToPay = new PromiseToPay();
        $this->assertTrue(self::$promiseToPay->create([
            'customer' => self::$customer,
            'invoice' => self::$invoice,
            'method' => PaymentMethod::CHECK,
            'date' => mktime(2015, 8, 31),
            'currency' => 'usd',
            'amount' => 100,
        ]));

        $this->assertEquals(self::$invoice->id(), self::$promiseToPay->invoice_id);
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$promiseToPay, EventType::InvoicePaymentExpected);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'amount' => 100.0,
            'broken' => false,
            'created_at' => self::$promiseToPay->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'date' => mktime(2015, 8, 31),
            'id' => self::$promiseToPay->id(),
            'invoice_id' => self::$invoice->id,
            'kept' => false,
            'method' => PaymentMethod::CHECK,
            'reference' => null,
            'updated_at' => self::$promiseToPay->updated_at,
        ];

        $this->assertEquals($expected, self::$promiseToPay->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEventObject(): void
    {
        $expected = [
            'amount' => 100.0,
            'broken' => false,
            'created_at' => self::$promiseToPay->created_at,
            'currency' => 'usd',
            'customer' => ModelNormalizer::toArray(self::$customer),
            'customer_id' => self::$customer->id,
            'date' => mktime(2015, 8, 31),
            'id' => self::$promiseToPay->id(),
            'invoice' => ModelNormalizer::toArray(self::$invoice),
            'invoice_id' => self::$invoice->id,
            'kept' => false,
            'method' => PaymentMethod::CHECK,
            'reference' => null,
            'updated_at' => self::$promiseToPay->updated_at,
        ];
        $this->assertEquals($expected, self::$promiseToPay->getEventObject());
    }

    /**
     * @depends testCreate
     */
    public function testInvoicePaid(): void
    {
        $promiseToPay = new PromiseToPay();
        $this->assertTrue($promiseToPay->create([
            'customer' => self::$customer,
            'invoice' => self::$invoice,
            'method' => PaymentMethod::CHECK,
            'date' => time() - 3600,
            'currency' => 'usd',
            'amount' => 100,
        ]));

        $this->assertFalse(self::$promiseToPay->refresh()->kept);
        $this->assertFalse($promiseToPay->refresh()->kept);
        self::hasTransaction();
        $this->assertTrue(self::$promiseToPay->refresh()->kept);
        $this->assertFalse($promiseToPay->refresh()->kept);
    }

    public function testKeptWithPayment(): void
    {
        EventSpool::enable();

        self::getService('test.event_spool')->flush();
        $event = Event::query()->sort('id DESC')->oneOrNull();
        $invoice = self::getTestDataFactory()->createInvoice(self::$customer);
        $promiseToPay = new PromiseToPay();
        $this->assertTrue($promiseToPay->create([
            'customer' => self::$customer,
            'invoice' => $invoice,
            'method' => PaymentMethod::CHECK,
            'date' => time() + 3600,
            'currency' => 'usd',
            'amount' => $invoice->balance,
        ]));

        $this->assertFalse($promiseToPay->refresh()->kept);
        $payment = new Payment();
        $payment->amount = $invoice->balance;
        $payment->applied_to = [['invoice' => $invoice, 'type' => 'invoice', 'amount' => $invoice->balance]];
        $payment->saveOrFail();
        $this->assertTrue($promiseToPay->refresh()->kept);

        self::getService('test.event_spool')->flush();
        /** @var Event[] $events */
        $events = Event::where('id', (int) $event?->id, '>')->sort('id ASC')->execute();
        $this->assertCount(4, $events);
        $this->assertEquals(EventType::InvoiceCreated->value, $events[0]->type);
        $this->assertEquals(EventType::InvoicePaymentExpected->value, $events[1]->type);
        $this->assertEquals(EventType::InvoicePaid->value, $events[2]->type);
        $this->assertEquals(EventType::PaymentCreated->value, $events[3]->type);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$promiseToPay->method = null;
        self::$promiseToPay->date = null;
        $this->assertTrue(self::$promiseToPay->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$promiseToPay->delete());
    }
}
