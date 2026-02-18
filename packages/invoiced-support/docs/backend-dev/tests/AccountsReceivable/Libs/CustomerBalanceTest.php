<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Tests\AppTestCase;

class CustomerBalanceTest extends AppTestCase
{
    private static Invoice $autopayInvoice;
    private static Invoice $paymentPlanInvoice;
    private static Invoice $pendingInvoice;
    private static CustomerBalanceGenerator $generator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::acceptsCreditCards();
        self::$autopayInvoice = new Invoice();
        self::$autopayInvoice->autopay = true;
        self::$autopayInvoice->setCustomer(self::$customer);
        self::$autopayInvoice->items = [['unit_cost' => 300]];
        self::$autopayInvoice->saveOrFail();

        self::$paymentPlanInvoice = new Invoice();
        self::$paymentPlanInvoice->setCustomer(self::$customer);
        self::$paymentPlanInvoice->items = [['unit_cost' => 300]];
        self::$paymentPlanInvoice->saveOrFail();

        $voidedInvoice = new Invoice();
        $voidedInvoice->date = strtotime('-1 month');
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 100]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('+1 day');
        $installment1->amount = 200;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+2 days');
        $installment2->amount = 50;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+1 month');
        $installment3->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
            $installment3,
        ];
        self::$paymentPlanInvoice->attachPaymentPlan($paymentPlan, false, true);

        self::$pendingInvoice = new Invoice();
        self::$pendingInvoice->setCustomer(self::$customer);
        self::$pendingInvoice->items = [['unit_cost' => 200]];
        self::$pendingInvoice->saveOrFail();

        $payment = new Transaction();
        $payment->status = Transaction::STATUS_PENDING;
        $payment->setInvoice(self::$pendingInvoice);
        $payment->amount = 200;
        $payment->saveOrFail();

        self::hasCredit();

        self::$creditNote = new CreditNote();
        self::$creditNote->setCustomer(self::$customer);
        self::$creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 50,
            ],
        ];
        self::$creditNote->saveOrFail();

        self::$generator = new CustomerBalanceGenerator(self::getService('test.database'));
    }

    public function testCalculate(): void
    {
        $balance = self::$generator->generate(self::$customer);

        $expected = [
            'available_credits' => 100.0,
            'currency' => 'usd',
            'history' => [
                [
                    'timestamp' => self::$credit->date,
                    'balance' => 100.0,
                    'currency' => 'usd',
                ],
            ],
            'total_outstanding' => 900.0,
            'due_now' => 100.0,
            'past_due' => false,
            'open_credit_notes' => 50.0,
        ];

        $this->assertEquals($expected, $balance->toArray());

        // test getters
        $this->assertEquals(100.0, $balance->availableCredits->toDecimal());
        $this->assertEquals('usd', $balance->currency);
        $this->assertEquals($expected['history'], $balance->history);
        $outstanding = $balance->totalOutstanding;
        $this->assertInstanceOf(Money::class, $outstanding);
        $this->assertEquals('usd', $outstanding->currency);
        $this->assertEquals(90000, $outstanding->amount);
        $this->assertFalse($balance->pastDue);
        $dueNow = $balance->dueNow;
        $this->assertInstanceOf(Money::class, $dueNow);
        $this->assertEquals('usd', $dueNow->currency);
        $this->assertEquals(10000, $dueNow->amount);
        $openCreditNotes = $balance->openCreditNotes;
        $this->assertInstanceOf(Money::class, $openCreditNotes);
        $this->assertEquals('usd', $openCreditNotes->currency);
        $this->assertEquals(5000, $openCreditNotes->amount);
    }

    public function testCalculatePastDue(): void
    {
        self::$invoice->due_date = strtotime('-1 hour');
        $this->assertTrue(self::$invoice->save());

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = time();
        $installment1->amount = 200;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+2 days');
        $installment2->amount = 50;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+1 month');
        $installment3->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
            $installment3,
        ];
        self::$paymentPlanInvoice->attachPaymentPlan($paymentPlan, false, true);

        $balance = self::$generator->generate(self::$customer);

        $expected = [
            'available_credits' => 100.0,
            'currency' => 'usd',
            'history' => [
                [
                    'timestamp' => self::$credit->date,
                    'balance' => 100.0,
                    'currency' => 'usd',
                ],
            ],
            'total_outstanding' => 900.0,
            'open_credit_notes' => 50.0,
            'due_now' => 300.0,
            'past_due' => true,
        ];

        $this->assertEquals($expected, $balance->toArray());
    }

    public function testCalculateDifferentCurrency(): void
    {
        $balance = self::$generator->generate(self::$customer, 'eur');

        $expected = [
            'available_credits' => 0.0,
            'currency' => 'eur',
            'history' => [],
            'total_outstanding' => 0.0,
            'due_now' => 0.0,
            'open_credit_notes' => 0.0,
            'past_due' => true,
        ];

        $this->assertEquals($expected, $balance->toArray());
    }
}
