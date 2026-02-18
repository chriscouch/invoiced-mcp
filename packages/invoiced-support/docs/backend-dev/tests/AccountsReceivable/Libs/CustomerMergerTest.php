<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Exception\CustomerMergeException;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLink;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Models\PaymentFlow;
use App\Tests\AppTestCase;

class CustomerMergerTest extends AppTestCase
{
    private static Customer $customer2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasTransaction();
        self::hasPayment();

        $paymentLink = new PaymentLink();
        $paymentLink->status = PaymentLinkStatus::Active;
        $paymentLink->reusable = true;
        $paymentLink->currency = 'usd';
        $paymentLink->customer = self::$customer;
        $paymentLink->saveOrFail();

        self::$customer2 = new Customer();
        self::$customer2->name = 'Merged Customer';
        self::$customer2->saveOrFail();

        self::$payment->setCustomer(self::$customer); // hasPayment() does not set customer.
        self::$payment->saveOrFail();

        self::makePaymentFlow(self::$customer, $paymentLink);
        self::makePaymentFlow(self::$customer2, $paymentLink);
    }

    public function testCannotMergeSameCustomer(): void
    {
        $this->expectException(CustomerMergeException::class);
        $merger = self::getService('test.customer_merger');
        $merger->merge(self::$customer2, self::$customer2);
    }

    public function testMerge(): void
    {
        $merger = self::getService('test.customer_merger');
        $merger->merge(self::$customer2, self::$customer);

        // merged in customer should no longer exist
        $this->assertNull(Customer::find(self::$customer->id()));

        // objects should be moved over
        $this->assertEquals(self::$customer2->id(), self::$invoice->refresh()->customer);
        $this->assertEquals(self::$customer2->id(), self::$transaction->refresh()->customer);
        $this->assertEquals(self::$customer2->id(), self::$payment->refresh()->customer);

        // should create an event
        self::getService('test.event_spool')->flush(); // write out events
        $numEvents = Event::where('type_id', EventType::CustomerMerged->toInteger())->count();
        $this->assertEquals(1, $numEvents);

        $this->assertCount(2, PaymentFlow::where('customer_id', self::$customer2->id())->execute());
    }

    /**
     * @depends testMerge
     */
    public function testCannotMergeTwice(): void
    {
        $this->expectException(CustomerMergeException::class);
        $merger = self::getService('test.customer_merger');
        $merger->merge(self::$customer2, self::$customer);
    }

    private static function makePaymentFlow(Customer $customer, PaymentLink $paymentLink): PaymentFlow
    {
        $manager = self::getService('test.payment_flow_manager');

        $flow = new PaymentFlow();
        $flow->payment_link = $paymentLink;
        $flow->amount = 0;
        $flow->currency = $paymentLink->currency;
        $flow->customer = $customer;
        $flow->initiated_from = PaymentFlowSource::CustomerPortal;
        $manager->create($flow);

        return $flow;
    }
}
