<?php

namespace App\Tests\PaymentPlans;

use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanApproval;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Tests\AppTestCase;

class PaymentPlanTest extends AppTestCase
{
    private static PaymentPlan $paymentPlan;
    private static Invoice $invoice2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::$invoice->autopay = true;
        self::$invoice->saveOrFail();

        self::$invoice2 = new Invoice();
        self::$invoice2->setCustomer(self::$customer);
        self::$invoice2->items = [['unit_cost' => 100]];
        self::$invoice2->amount_paid = 50;
        self::$invoice2->saveOrFail();
    }

    public function testEventAssociations(): void
    {
        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = 4567;
        $invoice = new Invoice(['id' => 4567]);
        $invoice->customer = 1234;
        $paymentPlan->setRelation('invoice_id', $invoice);

        $this->assertEquals([
            ['customer', 1234],
            ['invoice', 4567],
        ], $paymentPlan->getEventAssociations());
    }

    public function testEventObject(): void
    {
        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = (int) self::$invoice->id();
        $paymentPlan->installments = [];
        $paymentPlan->setRelation('invoice_id', self::$invoice);

        $this->assertEquals(array_merge($paymentPlan->toArray(), [
            'customer' => self::$customer->toArray(),
        ]), $paymentPlan->getEventObject());
    }

    public function testCreateZeroBalanceInvoice(): void
    {
        $installment = new PaymentPlanInstallment();
        $installment->date = (int) mktime(0, 0, 0, 8, 1, 2016);
        $installment->amount = 101;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = (int) self::$invoice->id();
        $paymentPlan->installments = [$installment];
        $this->assertFalse($paymentPlan->save());
    }

    public function testCreateIncorrectInstallmentAmount(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $this->assertTrue($invoice->save());

        $installment = new PaymentPlanInstallment();
        $installment->date = (int) mktime(0, 0, 0, 8, 1, 2016);
        $installment->amount = 100;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = (int) $invoice->id();
        $paymentPlan->installments = [$installment];
        $this->assertFalse($paymentPlan->save());
    }

    public function testCreateTooManyInstallments(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 101]];
        $this->assertTrue($invoice->save());

        $installment = new PaymentPlanInstallment();
        $installment->date = (int) mktime(0, 0, 0, 8, 1, 2016);
        $installment->amount = 1;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = (int) $invoice->id();
        $paymentPlan->installments = array_fill(0, 101, $installment);
        $this->assertFalse($paymentPlan->save());
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = (int) mktime(0, 0, 0, 8, 1, 2016);
        $installment1->amount = 25;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = (int) mktime(0, 0, 0, 9, 1, 2016);
        $installment2->amount = 25;

        $installment3 = new PaymentPlanInstallment();
        $installment3->date = (int) mktime(0, 0, 0, 10, 1, 2016);
        $installment3->amount = 50;

        self::$paymentPlan = new PaymentPlan();
        self::$paymentPlan->invoice_id = (int) self::$invoice->id();
        self::$paymentPlan->installments = [
            $installment1,
            $installment3,
            $installment2,
        ];
        $this->assertTrue(self::$paymentPlan->save());
        $this->assertEquals(self::$company->id(), self::$paymentPlan->tenant_id);

        // ensure installments were saved
        foreach (self::$paymentPlan->installments as $installment) {
            $this->assertGreaterThan(0, $installment->id());
        }

        self::$invoice->payment_plan_id = (int) self::$paymentPlan->id();
        $this->assertTrue(self::$invoice->save());
    }

    public function testCreatePartialPaid(): void
    {
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = (int) mktime(0, 0, 0, 8, 1, 2016);
        $installment1->amount = 25;
        $installment1->balance = 0;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = (int) mktime(0, 0, 0, 9, 1, 2016);
        $installment2->amount = 25;
        $installment2->balance = 0;

        $installment3 = new PaymentPlanInstallment();
        $installment3->date = (int) mktime(0, 0, 0, 10, 1, 2016);
        $installment3->amount = 50;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = (int) self::$invoice2->id();
        $paymentPlan->installments = [
            $installment1,
            $installment3,
            $installment2,
        ];
        $this->assertTrue($paymentPlan->save());
        $this->assertEquals(self::$company->id(), $paymentPlan->tenant_id);

        // ensure installments were saved
        foreach ($paymentPlan->installments as $installment) {
            $this->assertGreaterThan(0, $installment->id());
        }

        self::$invoice2->payment_plan_id = (int) self::$paymentPlan->id();
        $this->assertTrue(self::$invoice2->save());
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        $this->assertHasEvent(self::$paymentPlan, EventType::PaymentPlanCreated);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        EventSpool::enable();

        self::$paymentPlan->status = PaymentPlan::STATUS_FINISHED;
        $this->assertTrue(self::$paymentPlan->save());
    }

    /**
     * @depends testEdit
     */
    public function testEventEdited(): void
    {
        $this->assertHasEvent(self::$paymentPlan, EventType::PaymentPlanUpdated);
    }

    /**
     * @depends testCreate
     * @depends testCreatePartialPaid
     */
    public function testQuery(): void
    {
        $paymentPlans = PaymentPlan::all();

        $this->assertCount(2, $paymentPlans);
        $this->assertEquals(self::$paymentPlan->id(), $paymentPlans[0]->id());

        $this->assertCount(3, $paymentPlans[0]->installments);
    }

    /**
     * @depends testCreate
     */
    public function testApprove(): void
    {
        self::$paymentPlan->invoice()->refresh();
        self::$paymentPlan->status = PaymentPlan::STATUS_PENDING_SIGNUP;
        $this->assertTrue(self::$paymentPlan->save());

        $approval = self::getService('test.approve_payment_plan')->approve(self::$paymentPlan, '127.0.0.1', 'user-agent');

        // should build an approval
        $this->assertInstanceOf(PaymentPlanApproval::class, $approval);
        $this->assertEquals('127.0.0.1', $approval->ip);
        $this->assertEquals('user-agent', $approval->user_agent);
        $this->assertEquals(self::$paymentPlan->approval_id, $approval->id());

        // should schedule the next payment attempt
        $this->assertEquals((int) mktime(0, 0, 0, 8, 1, 2016), self::$invoice->refresh()->next_payment_attempt);
        $this->assertEquals(PaymentPlan::STATUS_ACTIVE, self::$paymentPlan->status);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$paymentPlan->id(),
            'object' => 'payment_plan',
            'status' => PaymentPlan::STATUS_ACTIVE,
            'installments' => [
                [
                    'date' => mktime(0, 0, 0, 8, 1, 2016),
                    'amount' => 25.0,
                    'balance' => 25.0,
                ],
                [
                    'date' => mktime(0, 0, 0, 9, 1, 2016),
                    'amount' => 25.0,
                    'balance' => 25.0,
                ],
                [
                    'date' => mktime(0, 0, 0, 10, 1, 2016),
                    'amount' => 50.0,
                    'balance' => 50.0,
                ],
            ],
            'approval' => [
                'id' => self::$paymentPlan->approval->id(), /* @phpstan-ignore-line */
                'ip' => '127.0.0.1',
                'timestamp' => self::$paymentPlan->approval->timestamp, /* @phpstan-ignore-line */
                'user_agent' => 'user-agent',
            ],
            'created_at' => self::$paymentPlan->created_at,
            'updated_at' => self::$paymentPlan->updated_at,
        ];

        $arr = self::$paymentPlan->toArray();
        foreach ($arr['installments'] as &$installment) {
            unset($installment['id']);
            unset($installment['updated_at']);
        }

        $this->assertEquals($expected, $arr);
    }

    /**
     * @depends testCreate
     */
    public function testApplyPayment(): void
    {
        $invoice = self::$paymentPlan->invoice();

        $payment = new Money('usd', 0);
        $this->assertFalse(self::$paymentPlan->applyPayment($payment));

        $invoice->attempt_count = 1;
        $payment = new Money('usd', 10100);
        $this->assertFalse(self::$paymentPlan->applyPayment($payment));
        $this->assertEquals(1, $invoice->attempt_count);

        // apply a $35 payment
        $invoice->attempt_count = 1;
        $payment = new Money('usd', 3500);
        $this->assertTrue(self::$paymentPlan->applyPayment($payment));
        $this->assertEquals(0, $invoice->attempt_count);

        // verify installment balances
        $this->assertEquals(0, self::$paymentPlan->installments[0]->balance);
        $this->assertEquals(15, self::$paymentPlan->installments[1]->balance);
        $this->assertEquals(50, self::$paymentPlan->installments[2]->balance);

        // apply another $35 payment
        $invoice->attempt_count = 1;
        $this->assertTrue(self::$paymentPlan->applyPayment($payment));
        $this->assertEquals(0, $invoice->attempt_count);

        // verify installment balances
        $this->assertEquals(0, self::$paymentPlan->installments[0]->balance);
        $this->assertEquals(0, self::$paymentPlan->installments[1]->balance);
        $this->assertEquals(30, self::$paymentPlan->installments[2]->balance);

        // apply the final payment
        $invoice->attempt_count = 1;
        $payment = new Money('usd', 3000);
        $this->assertTrue(self::$paymentPlan->applyPayment($payment));
        $this->assertEquals(1, $invoice->attempt_count);

        // verify installment balances
        $this->assertEquals(0, self::$paymentPlan->installments[0]->balance);
        $this->assertEquals(0, self::$paymentPlan->installments[1]->balance);
        $this->assertEquals(0, self::$paymentPlan->installments[2]->balance);

        // should mark plan as finished once the balances are 0
        $this->assertEquals(PaymentPlan::STATUS_FINISHED, self::$paymentPlan->status);
        $this->assertNull($invoice->next_payment_attempt);

        // try a refund
        $invoice->attempt_count = 1;
        $refund = new Money('usd', -3000);
        $this->assertTrue(self::$paymentPlan->applyPayment($refund));
        $this->assertEquals(PaymentPlan::STATUS_ACTIVE, self::$paymentPlan->status);
        $this->assertGreaterThan(0, $invoice->next_payment_attempt);
        $this->assertEquals(0, $invoice->attempt_count);

        // verify installment balances
        $this->assertEquals(0, self::$paymentPlan->installments[0]->balance);
        $this->assertEquals(0, self::$paymentPlan->installments[1]->balance);
        $this->assertEquals(30, self::$paymentPlan->installments[2]->balance);

        // apply another refund
        $refund = new Money('usd', -3000);
        $this->assertTrue(self::$paymentPlan->applyPayment($refund));
        $this->assertEquals(PaymentPlan::STATUS_ACTIVE, self::$paymentPlan->status);
        $this->assertGreaterThan(0, $invoice->next_payment_attempt);

        // verify installment balances
        $this->assertEquals(0, self::$paymentPlan->installments[0]->balance);
        $this->assertEquals(10, self::$paymentPlan->installments[1]->balance);
        $this->assertEquals(50, self::$paymentPlan->installments[2]->balance);
    }

    /**
     * @depends testCreate
     */
    public function testCancel(): void
    {
        EventSpool::enable();

        self::$paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        $this->assertTrue(self::$paymentPlan->save());
        $installment = self::$paymentPlan->installments[2];
        $installment->balance = 30;
        $this->assertTrue($installment->save());

        $this->assertTrue(self::$paymentPlan->cancel());

        $this->assertEquals(PaymentPlan::STATUS_CANCELED, self::$paymentPlan->status);
        $this->assertNull(self::$invoice->refresh()->payment_plan);
        $this->assertNull(self::$invoice->next_payment_attempt);
        $this->assertNull(self::$invoice->payment_terms);
        $this->assertNull(self::$invoice->due_date);
        $this->assertFalse(self::$invoice->autopay);
    }

    /**
     * @depends testCancel
     */
    public function testEventDeleted(): void
    {
        $this->assertHasEvent(self::$paymentPlan, EventType::PaymentPlanDeleted);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$paymentPlan->delete());
    }
}
