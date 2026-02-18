<?php

namespace App\Tests\Statements\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Statements\Libs\BalanceForwardStatement;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class BalanceForwardStatementTest extends AppTestCase
{
    private static Invoice $invoice2;
    private static Transaction $payment2;
    private static Transaction $paymentMain;
    private static Transaction $payment3;
    private static Transaction $payment4;
    private static Transaction $unappliedPayment;
    private static int $time;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$time = time();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->items = [['unit_cost' => 200]];
        self::$invoice->saveOrFail();

        self::$creditNote = new CreditNote();
        self::$creditNote->setCustomer(self::$customer);
        self::$creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        self::$creditNote->saveOrFail();

        // partially apply credit note
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 0;
        $payment->applied_to = [[
            'type' => 'credit_note',
            'credit_note' => self::$creditNote,
            'document_type' => 'invoice',
            'invoice' => self::$invoice,
            'amount' => 10,
        ]];
        $payment->saveOrFail();

        // make a partial payment
        self::$payment = new Payment();
        self::$payment->setCustomer(self::$customer);
        self::$payment->amount = 100;
        self::$payment->applied_to = [[
            'type' => 'invoice',
            'invoice' => self::$invoice,
            'amount' => 100,
        ]];
        self::$payment->saveOrFail();

        self::$company->accounts_receivable_settings->auto_apply_credits = false;
        self::$company->accounts_receivable_settings->saveOrFail();

        self::$credit = new CreditBalanceAdjustment();
        self::$credit->setCustomer(self::$customer);
        self::$credit->date = self::$time + 1;
        self::$credit->amount = 10;
        self::$credit->saveOrFail();

        self::$invoice2 = new Invoice();
        self::$invoice2->setCustomer(self::$customer);
        self::$invoice2->date = self::$time + 2;
        self::$invoice2->items = [[
            'quantity' => 1,
            'unit_cost' => 20, ]];
        self::$invoice2->saveOrFail();

        self::$payment2 = new Transaction();
        self::$payment2->type = Transaction::TYPE_CHARGE;
        self::$payment2->setInvoice(self::$invoice2);
        self::$payment2->method = PaymentMethod::BALANCE;
        self::$payment2->date = self::$time + 3;
        self::$payment2->amount = 10;
        self::$payment2->saveOrFail();

        self::$paymentMain = new Transaction();
        self::$paymentMain->type = Transaction::TYPE_CHARGE;
        self::$paymentMain->method = PaymentMethod::CREDIT_CARD;
        self::$paymentMain->date = self::$time + 4;
        self::$paymentMain->amount = 4;
        self::$paymentMain->setCustomer(self::$customer);
        self::$paymentMain->saveOrFail();

        self::$payment3 = new Transaction();
        self::$payment3->type = Transaction::TYPE_CHARGE;
        self::$payment3->method = PaymentMethod::CREDIT_CARD;
        self::$payment3->date = self::$time + 4;
        self::$payment3->setParentTransaction(self::$paymentMain);
        self::$payment3->amount = 1;
        self::$payment3->setCustomer(self::$customer);
        self::$payment3->markConvenienceFee();
        self::$payment3->saveOrFail();

        self::$payment4 = new Transaction();
        self::$payment4->type = Transaction::TYPE_REFUND;
        self::$payment4->method = PaymentMethod::CREDIT_CARD;
        self::$payment4->date = self::$time + 5;
        self::$payment4->amount = 1;
        self::$payment4->markConvenienceFee();
        self::$payment4->setParentTransaction(self::$payment3);
        self::$payment4->setCustomer(self::$customer);
        self::$payment4->saveOrFail();

        self::$unappliedPayment = new Transaction();
        self::$unappliedPayment->setCustomer(self::$customer);
        self::$unappliedPayment->date = self::$time + 5;
        self::$unappliedPayment->amount = 70;
        self::$unappliedPayment->saveOrFail();

        $refund = new Transaction();
        $refund->setCustomer(self::$customer);
        $refund->setInvoice(self::$invoice2);
        $refund->type = Transaction::TYPE_REFUND;
        $refund->amount = 5;
        $refund->setParentTransaction(self::$payment2);
        $refund->saveOrFail();

        $voidedInvoice = new Invoice();
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 1000]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();
    }

    private function getStatement(Customer $customer, ?string $currency = null, ?int $start = null, ?int $end = null): BalanceForwardStatement
    {
        return self::getService('test.statement_builder')->balanceForward($customer, $currency, $start, $end);
    }

    public function testDateGetters(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $statement = $this->getStatement($customer);

        $this->assertTimestampsEqual((int) mktime(0, 0, 0, (int) date('m'), 1, (int) date('Y')), (int) $statement->getStartDate());
        $delta = abs(time() - $statement->getEndDate());
        $this->assertBetween($delta, 0, 3);

        $statement = $this->getStatement($customer, null, 1, 2);
        $this->assertEquals(1, $statement->getStartDate());
        $this->assertEquals(2, $statement->getEndDate());
    }

    public function testGetPreviousStatement(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $statement = $this->getStatement($customer, null, 0);

        $this->assertNull($statement->getPreviousStatement());

        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $statement = $this->getStatement($customer, null, 2);
        /** @var BalanceForwardStatement $previous */
        $previous = $statement->getPreviousStatement();

        $this->assertInstanceOf(BalanceForwardStatement::class, $previous);
        $this->assertEquals(0, $previous->getStartDate());
        $this->assertEquals(1, $previous->getEndDate());
    }

    public function testGetCurrency(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $statement = $this->getStatement($customer);

        $this->assertEquals('usd', $statement->getCurrency());

        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $company = $customer->tenant();
        $company->currency = 'nzd';
        $statement = $this->getStatement($customer);

        $this->assertEquals('nzd', $statement->getCurrency());
    }

    //
    // Balance Forward
    //

    public function testCalculateNoData(): void
    {
        self::$company->currency = 'usd';
        $customer = new Customer(['id' => -1]);
        $customer->tenant_id = (int) self::$company->id();
        $statement = $this->getStatement($customer);

        $this->assertEquals('usd', $statement->getCurrency());

        $this->assertEquals(0.0, $statement->previousBalance);
        $this->assertEquals(0.0, $statement->totalInvoiced);
        $this->assertEquals(0.0, $statement->totalPaid);
        $this->assertEquals(0.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(0.0, $statement->totalCreditsIssued);
        $this->assertEquals(0.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);
        $this->assertEquals([
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'customer' => null,
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
        ], $statement->accountDetail);
        $this->assertEquals([], $statement->creditDetail);
    }

    public function testCalculate(): void
    {
        $statement = $this->getStatement(self::$customer, null, 0, self::$time + 100);

        $this->assertEquals('usd', $statement->getCurrency());

        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(120.0, $statement->totalInvoiced);
        $this->assertEquals(179.0, $statement->totalPaid);
        $this->assertEquals(-59.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(10.0, $statement->totalCreditsIssued);
        $this->assertEquals(10.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $accountDetail = $statement->accountDetail;
        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
            [
                'number' => 'INV-00001',
                'url' => self::$invoice->url,
                'date' => self::$invoice->date,
                'invoiced' => 200.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 200.0,
                'balance' => 200.0,
            ],
            [
                'number' => 'CN-00001',
                'url' => self::$creditNote->url,
                'date' => self::$creditNote->date,
                'invoiced' => -100.0,
                '_type' => 'credit_note',
                'type' => 'Credit Note',
                'amount' => -100.0,
                'balance' => 100.0,
            ],
            [
                'number' => 'Payment',
                'date' => self::$payment->date,
                'paid' => 100.0,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -100.0,
                'balance' => 0.0,
            ],
            [
                'number' => null,
                'date' => self::$payment2->tree()->toArray()[0]['date'],
                '_type' => 'refund',
                'type' => 'Refund',
                'amount' => 5.0,
                'balance' => 5.0,
                'paid' => -5.0,
            ],
            [
                'number' => 'INV-00002',
                'url' => self::$invoice2->url,
                'date' => self::$invoice2->date,
                'invoiced' => 20.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 20.0,
                'balance' => 25.0,
            ],
            [
                'number' => 'Payment',
                'date' => self::$paymentMain->date,
                'paid' => 5.0,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -5.0,
                'balance' => 11.0,
            ],
            [
                'number' => 'Payment',
                'date' => self::$unappliedPayment->date,
                'paid' => 70.0,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -70.0,
                'balance' => -59.0,
            ],
            [
                'number' => null,
                'date' => self::$payment4->date,
                'paid' => -1.0,
                '_type' => Transaction::TYPE_REFUND,
                'type' => 'Refund',
                'amount' => 1.0,
                'balance' => -59.0,
            ],
        ];

        unset($accountDetail[0]['customer']);
        unset($accountDetail[1]['customer']);
        $this->assertInstanceOf(Invoice::class, $accountDetail[1]['invoice']);
        unset($accountDetail[1]['invoice']);
        unset($accountDetail[2]['customer']);
        $this->assertInstanceOf(CreditNote::class, $accountDetail[2]['creditNote']);
        unset($accountDetail[2]['creditNote']);
        unset($accountDetail[3]['customer']);
        unset($accountDetail[4]['customer']);
        unset($accountDetail[5]['customer']);
        $this->assertInstanceOf(Invoice::class, $accountDetail[5]['invoice']);
        unset($accountDetail[5]['invoice']);
        unset($accountDetail[6]['customer']);
        unset($accountDetail[7]['customer']);
        unset($accountDetail[8]['customer']);

        $this->assertEquals($expected, $accountDetail);

        $expected = [
            [
                'description' => 'Credit',
                'date' => self::$credit->date,
                'issued' => 10.0,
                '_type' => 'adjustment',
                'type' => 'Credit',
                'amount' => 10.0,
                'creditBalance' => 10.0,
            ],
            [
                'description' => 'Applied Credit: INV-00002',
                'date' => self::$payment2->date,
                'charged' => 10.0,
                '_type' => 'adjustment',
                'type' => 'Applied Credit',
                'number' => 'Applied Credit: INV-00002',
                'url' => self::$invoice2->url,
                'amount' => -10.0,
                'balance' => 15.0,
                'creditBalance' => 0.0,
            ],
        ];

        $creditDetail = $statement->creditDetail;
        unset($creditDetail[0]['customer']);
        unset($creditDetail[1]['customer']);

        $this->assertEquals($expected, $creditDetail);
    }

    public function testCalculateBalanceForwardWithDateRange(): void
    {
        $statement = $this->getStatement(self::$customer, null, strtotime('-1 year'), self::$time + 100);
        $this->assertEquals('usd', $statement->getCurrency());

        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(120.0, $statement->totalInvoiced);
        $this->assertEquals(179.0, $statement->totalPaid);
        $this->assertEquals(0, $statement->totalUnapplied);
        $this->assertEquals(-59.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(10.0, $statement->totalCreditsIssued);
        $this->assertEquals(10.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $accountDetail = $statement->accountDetail;
        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
            [
                'number' => 'INV-00001',
                'url' => self::$invoice->url,
                'date' => self::$invoice->date,
                'invoiced' => 200.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 200.0,
                'balance' => 200.0,
            ],
            [
                'number' => 'CN-00001',
                'url' => self::$creditNote->url,
                'date' => self::$creditNote->date,
                'invoiced' => -100.0,
                '_type' => 'credit_note',
                'type' => 'Credit Note',
                'amount' => -100.0,
                'balance' => 100.0,
            ],
            [
                'number' => 'Payment',
                'date' => self::$payment->date,
                'paid' => 100.0,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -100.0,
                'balance' => 0.0,
            ],
            [
                'number' => null,
                'date' => self::$payment2->tree()->toArray()[0]['date'],
                '_type' => 'refund',
                'type' => 'Refund',
                'amount' => 5.0,
                'balance' => 5.0,
                'paid' => -5.0,
            ],
            [
                'number' => 'INV-00002',
                'url' => self::$invoice2->url,
                'date' => self::$invoice2->date,
                'invoiced' => 20.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 20.0,
                'balance' => 25.0,
            ],

            [
                'number' => 'Payment',
                'date' => self::$paymentMain->date,
                'paid' => 5.0,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -5.0,
                'balance' => 11.0,
            ],
            [
                'number' => 'Payment',
                'date' => self::$unappliedPayment->date,
                'paid' => 70.0,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -70.0,
                'balance' => -59.0,
            ],
            [
                'number' => null,
                'date' => self::$payment4->date,
                'paid' => -1.0,
                '_type' => Transaction::TYPE_REFUND,
                'type' => 'Refund',
                'amount' => 1.0,
                'balance' => -59.0,
            ],
        ];

        unset($accountDetail[0]['customer']);
        unset($accountDetail[1]['customer']);
        $this->assertInstanceOf(Invoice::class, $accountDetail[1]['invoice']);
        unset($accountDetail[1]['invoice']);
        unset($accountDetail[2]['customer']);
        $this->assertInstanceOf(CreditNote::class, $accountDetail[2]['creditNote']);
        unset($accountDetail[2]['creditNote']);
        unset($accountDetail[3]['customer']);
        unset($accountDetail[4]['customer']);
        unset($accountDetail[5]['customer']);
        $this->assertInstanceOf(Invoice::class, $accountDetail[5]['invoice']);
        unset($accountDetail[5]['invoice']);
        unset($accountDetail[6]['customer']);
        unset($accountDetail[7]['customer']);
        unset($accountDetail[8]['customer']);

        $this->assertEquals($expected, $accountDetail);

        $expected = [
            [
                'description' => 'Credit',
                'date' => self::$credit->date,
                'issued' => 10.0,
                '_type' => 'adjustment',
                'type' => 'Credit',
                'amount' => 10.0,
                'creditBalance' => 10.0,
            ],
            [
                'description' => 'Applied Credit: INV-00002',
                'date' => self::$payment2->date,
                'charged' => 10.0,
                '_type' => 'adjustment',
                'type' => 'Applied Credit',
                'number' => 'Applied Credit: INV-00002',
                'url' => self::$invoice2->url,
                'amount' => -10.0,
                'balance' => 15.0,
                'creditBalance' => 0.0,
            ],
        ];

        $creditDetail = $statement->creditDetail;
        unset($creditDetail[0]['customer']);
        unset($creditDetail[1]['customer']);

        $this->assertEquals($expected, $creditDetail);

        $expected = [
            [
                'lower' => 0,
                'amount' => 0.0,
                'count' => 2,
            ],
            [
                'lower' => 8,
                'amount' => 0.0,
                'count' => 0,
            ],
            [
                'lower' => 15,
                'amount' => 0.0,
                'count' => 0,
            ],
            [
                'lower' => 31,
                'amount' => 0.0,
                'count' => 0,
            ],
            [
                'lower' => 61,
                'amount' => 0.0,
                'count' => 0,
            ],
        ];

        $this->assertEquals($expected, $statement->aging);
    }

    public function testCalculateInvd1678(): void
    {
        $customer = new Customer();
        $customer->name = 'INVD-1678';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 40]];
        $invoice->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer);
        $invoice2->items = [['unit_cost' => 45]];
        $invoice2->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->currency = 'usd';
        $payment->amount = 45;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice->id(),
                'amount' => 40,
            ],
            [
                'type' => PaymentItemType::Credit->value,
                'amount' => 5,
            ],
        ];
        $payment->saveOrFail();

        $creditApplication = new Transaction();
        $creditApplication->type = Transaction::TYPE_CHARGE;
        $creditApplication->method = PaymentMethod::BALANCE;
        $creditApplication->setInvoice($invoice2);
        $creditApplication->amount = 5;
        $creditApplication->saveOrFail();

        $statement = $this->getStatement($customer);

        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(85.0, $statement->totalInvoiced);
        $this->assertEquals(45.0, $statement->totalPaid);
        $this->assertEquals(0, $statement->totalUnapplied);
        $this->assertEquals(40.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(5.0, $statement->totalCreditsIssued);
        $this->assertEquals(5.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);
    }

    public function testCalculateMultipleAppliedCredits(): void
    {
        $customer = new Customer();
        $customer->name = 'INVD-1679';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 50]];
        $invoice->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer);
        $invoice2->items = [['unit_cost' => 55]];
        $invoice2->saveOrFail();

        // Setup data, adds credits to credit balance in order to
        // test applied_credit splits.
        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->currency = 'usd';
        $payment->amount = 10;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Credit->value,
                'amount' => 10,
            ],
        ];
        $payment->saveOrFail();

        // Test payment
        $payment2 = new Payment();
        $payment2->setCustomer($customer);
        $payment2->currency = 'usd';
        $payment2->amount = 0;
        $payment2->applied_to = [
            [
                'type' => PaymentItemType::AppliedCredit->value,
                'document_type' => 'invoice',
                'invoice' => $invoice->id(),
                'amount' => 5,
            ],
            [
                'type' => PaymentItemType::AppliedCredit->value,
                'document_type' => 'invoice',
                'invoice' => $invoice2->id(),
                'amount' => 5,
            ],
        ];
        $payment2->saveOrFail();

        $statement = $this->getStatement($customer);

        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(105.0, $statement->totalInvoiced);
        $this->assertEquals(10.0, $statement->totalPaid);
        $this->assertEquals(0, $statement->totalUnapplied);
        $this->assertEquals(95.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(10.0, $statement->totalCreditsIssued);
        $this->assertEquals(10.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $lines = $statement->unifiedDetail;
        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
            [
                'number' => 'INV-00006',
                'url' => $invoice->url,
                'date' => $invoice->date,
                'invoiced' => 50.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 50.0,
                'balance' => 50.0,
            ],
            [
                'number' => 'INV-00007',
                'url' => $invoice2->url,
                'date' => $invoice2->date,
                'invoiced' => 55.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 55.0,
                'balance' => 105.0,
            ],
            [
                'number' => 'Applied Credit: INV-00006',
                'description' => 'Applied Credit: INV-00006',
                'url' => $invoice->url,
                'date' => $payment2->date,
                '_type' => 'adjustment',
                'type' => 'Applied Credit',
                'amount' => -5.0,
                'balance' => 100.0,
                'creditBalance' => -5.0,
                'charged' => 5.0,
            ],
            [
                'number' => 'Applied Credit: INV-00007',
                'description' => 'Applied Credit: INV-00007',
                'url' => $invoice2->url,
                'date' => $payment2->date,
                '_type' => 'adjustment',
                'type' => 'Applied Credit',
                'amount' => -5.0,
                'balance' => 95.0,
                'creditBalance' => -10.0,
                'charged' => 5.0,
            ],
            [
                'date' => $payment->date,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -10.0,
                'number' => 'Payment',
                'paid' => 10.0,
                'balance' => 95.0,
            ],
            [
                'date' => $payment->date,
                '_type' => 'adjustment',
                'type' => 'Credit',
                'description' => 'Credit',
                'amount' => 10.0,
                'issued' => 10.0,
                'creditBalance' => 0.0,
            ],
        ];

        unset($lines[0]['customer']);
        $this->assertInstanceOf(Invoice::class, $lines[1]['invoice']);
        unset($lines[1]['invoice']);
        unset($lines[1]['customer']);
        $this->assertInstanceOf(Invoice::class, $lines[2]['invoice']);
        unset($lines[2]['invoice']);
        unset($lines[2]['customer']);
        unset($lines[3]['customer']);
        unset($lines[4]['customer']);
        unset($lines[5]['customer']);
        unset($lines[6]['customer']);

        $this->assertEquals($expected, $lines);
    }

    public function testCalculateParentCustomer(): void
    {
        $parentCustomer = new Customer();
        $parentCustomer->name = 'Parent';
        $parentCustomer->saveOrFail();

        $subCustomer = new Customer();
        $subCustomer->name = 'Sub Customer';
        $subCustomer->setParentCustomer($parentCustomer);
        $subCustomer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($subCustomer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $statement = $this->getStatement($parentCustomer);

        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(100.0, $statement->totalInvoiced);
        $this->assertEquals(0.0, $statement->totalPaid);
        $this->assertEquals(0, $statement->totalUnapplied);
        $this->assertEquals(100.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(0.0, $statement->totalCreditsIssued);
        $this->assertEquals(0.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $lines = $statement->unifiedDetail;
        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
            [
                'number' => 'INV-00008',
                'url' => $invoice->url,
                'date' => $invoice->date,
                'invoiced' => 100.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 100.0,
                'balance' => 100.0,
            ],
        ];

        $this->assertInstanceOf(Invoice::class, $lines[1]['invoice']);
        unset($lines[0]['customer']);
        unset($lines[1]['customer']);
        unset($lines[1]['invoice']);

        $this->assertEquals($expected, $lines);
    }

    //
    // Emailing
    //

    /**
     * @doesNotPerformAssertions
     */
    public function testEmail(): void
    {
        $statement = $this->getStatement(self::$customer);

        $emailTemplate = (new DocumentEmailTemplateFactory())->get($statement);
        self::getService('test.email_spool')->spoolDocument($statement, $emailTemplate)->flush();
    }

    public function testINVD2154(): void
    {
        self::hasCustomer();

        self::hasUnappliedCreditNote();
        // backdate credit note for proper ordering
        --self::$creditNote->date;
        self::$creditNote->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->applied_to = [[
            'type' => PaymentItemType::CreditNote->value,
            'credit_note' => self::$creditNote->id,
            'amount' => 100,
        ]];
        $payment->saveOrFail();

        self::hasInvoice();
        self::$invoice->applyCredits();

        $statement = $this->getStatement(self::$customer);
        $result = $statement->getValues();
        unset($result['accountDetail'][0]['customer']);
        unset($result['accountDetail'][1]['customer']);
        unset($result['accountDetail'][2]['customer']);
        unset($result['creditDetail'][0]['customer']);
        unset($result['creditDetail'][1]['customer']);
        unset($result['unifiedDetail'][0]['customer']);
        unset($result['unifiedDetail'][1]['customer']);
        unset($result['unifiedDetail'][2]['customer']);
        unset($result['unifiedDetail'][3]['customer']);
        unset($result['unifiedDetail'][4]['customer']);

        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
            [
                'number' => self::$creditNote->number,
                'url' => self::$creditNote->url,
                'date' => self::$creditNote->date,
                'invoiced' => -100.0,
                '_type' => 'credit_note',
                'type' => 'Credit Note',
                'amount' => -100.0,
                'balance' => -100,
            ],
            [
                'number' => self::$invoice->number,
                'url' => self::$invoice->url,
                'date' => self::$invoice->date,
                'invoiced' => 100.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 100.0,
                'balance' => 0,
            ],
        ];

        $expectedCreditBalance = [
            [
                '_type' => 'adjustment',
                'type' => 'Applied Credit',
                'description' => 'Applied Credit: '.self::$invoice->number,
                'number' => 'Applied Credit: '.self::$invoice->number,
                'url' => self::$invoice->url,
                'date' => $payment->date,
                'charged' => 100,
                'amount' => -100,
                'balance' => -100,
                'creditBalance' => -100,
            ], [
                '_type' => 'adjustment',
                'description' => 'Credit',
                'type' => 'Credit',
                'date' => $payment->date,
                'issued' => 100,
                'amount' => 100,
                'creditBalance' => 0,
            ],
        ];

        $expectedUnifiedDetail = array_merge($expected, $expectedCreditBalance);

        unset($result['accountDetail'][1]['creditNote']);
        unset($result['accountDetail'][2]['invoice']);
        unset($result['unifiedDetail'][1]['creditNote']);
        unset($result['unifiedDetail'][2]['invoice']);
        $this->assertEquals(0, $result['balance']);
        $this->assertEquals(0, $result['previousBalance']);
        $this->assertEquals(0, $result['totalInvoiced']);
        $this->assertEquals(0, $result['totalUnapplied']);
        $this->assertEquals(0, $result['previousCreditBalance']);
        $this->assertEquals(100, $result['totalCreditsIssued']);
        $this->assertEquals(100, $result['totalCreditsSpent']);
        $this->assertEquals(0, $result['creditBalance']);
        $this->assertEquals($expected, $result['accountDetail']);
        $this->assertEquals($expectedCreditBalance, $result['creditDetail']);
        $this->assertEquals($expectedUnifiedDetail, $result['unifiedDetail']);
    }

    public function testINVD2243(): void
    {
        self::hasCustomer();

        self::hasEstimate();
        self::$estimate->deposit = 100;
        self::$estimate->saveOrFail();

        // backdate credit note for proper ordering
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->applied_to = [[
            'type' => PaymentItemType::Estimate->value,
            'amount' => 100,
            'estimate' => self::$estimate->id(),
        ]];
        $payment->saveOrFail();
        $this->verifyCreditPayment(self::$customer, $payment);
    }

    public function testINVD2244(): void
    {
        self::hasCustomer();

        // backdate credit note for proper ordering
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->applied_to = [[
            'type' => PaymentItemType::Credit->value,
            'amount' => 100,
        ]];
        $payment->saveOrFail();

        $this->verifyCreditPayment(self::$customer, $payment);
    }

    /**
     * Tests that the BalanceForwardStatement handles the
     * document adjustment split type.
     */
    public function testDocumentAdjustmentSplit(): void
    {
        self::hasCustomer();

        // NOTE:
        // Dates are set on the invoice, credit note and payments
        // to simulate sequential events. E.g. the invoice date
        // is before the payment date.

        $month = CarbonImmutable::now()->month;
        $year = CarbonImmutable::now()->year;

        // Create a CreditNote and Invoice both for $100
        $creditNote = new CreditNote();
        $creditNote->date = (int) mktime(11, 0, 0, $month, 1, $year);
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            ['unit_cost' => 100],
        ];
        $creditNote->saveOrFail();
        $invoice = new Invoice();
        $invoice->date = (int) mktime(11, 0, 0, $month, 1, $year);
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            ['unit_cost' => 100],
        ];
        $invoice->saveOrFail();

        $payment = new Payment();
        $payment->date = (int) mktime(12, 0, 0, $month, 1, $year);
        $payment->setCustomer(self::$customer);
        $payment->amount = 0;
        $payment->applied_to = [
            // Taking $80 off the invoice balance to leave it at $20
            [
                'type' => PaymentItemType::DocumentAdjustment->value,
                'amount' => 80,
                'document_type' => 'invoice',
                'invoice' => $invoice->id(),
            ],
            // Taking $80 off the credit note balance to leave it at $20
            [
                'type' => PaymentItemType::DocumentAdjustment->value,
                'amount' => 80,
                'document_type' => 'credit_note',
                'credit_note' => $creditNote->id(),
            ],
        ];
        $payment->saveOrFail();
        $payment2 = new Payment();
        $payment2->date = (int) mktime(12, 0, 0, $month, 1, $year);
        $payment2->setCustomer(self::$customer);
        $payment2->amount = 0;
        $payment2->applied_to = [
            // Apply $10 from the credit note's remaining $20 to the invoice
            [
                'type' => PaymentItemType::CreditNote->value,
                'amount' => 10,
                'credit_note' => $creditNote->id(),
                'document_type' => 'invoice',
                'invoice' => $invoice->id(),
            ],
        ];
        $payment2->saveOrFail();
        $payment3 = new Payment();
        $payment3->date = (int) mktime(12, 0, 0, $month, 1, $year);
        $payment3->setCustomer(self::$customer);
        $payment3->amount = 10;
        $payment3->applied_to = [
            // Pay the remaining $10 of the invoice
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => 10,
                'invoice' => $invoice->id(),
            ],
        ];
        $payment3->saveOrFail();

        // The payment should:
        // - fully pay off the invoice
        // - leave the credit note w/ 10 remaining
        $statement = $this->getStatement(self::$customer);
        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(0.0, $statement->totalInvoiced); // credit note subtracts from amount invoiced
        $this->assertEquals(10.0, $statement->totalPaid); // The credit note split does not add to amount paid
        $this->assertEquals(0, $statement->totalUnapplied);
        $this->assertEquals(-10.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(0.0, $statement->totalCreditsIssued);
        $this->assertEquals(0.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $lines = $statement->unifiedDetail;
        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
            [
                'number' => $invoice->number,
                'url' => $invoice->url,
                'date' => $invoice->date,
                'invoiced' => 100.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 100.0,
                'balance' => 100,
            ],
            [
                'number' => $creditNote->number,
                'url' => $creditNote->url,
                'date' => $creditNote->date,
                'invoiced' => -100.0,
                '_type' => 'credit_note',
                'type' => 'Credit Note',
                'amount' => -100.0,
                'balance' => 0,
            ],
            [
                '_type' => 'adjustment',
                'type' => 'Adjustment',
                'number' => 'Adjustment ('.$invoice->number.')',
                'description' => 'Adjustment ('.$invoice->number.')',
                'date' => $payment->date,
                'amount' => -80.0,
                'paid' => 80,
                'balance' => -80,
            ],
            [
                '_type' => 'adjustment',
                'type' => 'Adjustment',
                'number' => 'Adjustment ('.$creditNote->number.')',
                'description' => 'Adjustment ('.$creditNote->number.')',
                'date' => $payment->date,
                'amount' => 80.0,
                'paid' => -80,
                'balance' => 0,
            ],
            [
                'date' => $payment->date,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -10,
                'number' => 'Payment',
                'paid' => 10,
                'balance' => -10,
            ],
        ];

        unset($lines[0]['customer']);
        $this->assertInstanceOf(Invoice::class, $lines[1]['invoice']);
        unset($lines[1]['invoice']);
        unset($lines[1]['customer']);
        $this->assertInstanceOf(CreditNote::class, $lines[2]['creditNote']);
        unset($lines[2]['creditNote']);
        unset($lines[2]['customer']);
        unset($lines[3]['customer']);
        unset($lines[4]['customer']);
        unset($lines[5]['customer']);

        $this->assertEquals($expected, $lines);
    }

    /**
     * Tests that balance forward statements include transactions made from parent customers
     * towards documents owned by child customers.
     */
    public function testParentPayments(): void
    {
        $month = CarbonImmutable::now()->month;
        $year = CarbonImmutable::now()->year;

        $parentCustomer = new Customer();
        $parentCustomer->name = 'Parent';
        $parentCustomer->email = 'parent@example.com';
        $parentCustomer->saveOrFail();
        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->date = (int) mktime(12, 0, 0, $month, 1, $year);
        self::$invoice->saveOrFail();
        self::$customer->setParentCustomer($parentCustomer);
        self::$customer->saveOrFail();

        $payment = new Payment();
        $payment->date = (int) mktime(12, 0, 0, $month, 1, $year);
        $payment->setCustomer($parentCustomer);
        $payment->amount = self::$invoice->total;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => self::$invoice->total,
                'invoice' => self::$invoice->id(),
            ],
        ];
        $payment->saveOrFail();

        $statement = $this->getStatement(self::$customer);
        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(100.0, $statement->totalInvoiced);
        $this->assertEquals(100.0, $statement->totalPaid);
        $this->assertEquals(0, $statement->totalUnapplied);
        $this->assertEquals(0.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(0.0, $statement->totalCreditsIssued);
        $this->assertEquals(0.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $lines = $statement->unifiedDetail;
        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
            [
                'number' => self::$invoice->number,
                'url' => self::$invoice->url,
                'date' => self::$invoice->date,
                'invoiced' => 100.0,
                '_type' => 'invoice',
                'type' => 'Invoice',
                'amount' => 100.0,
                'balance' => 100,
            ],
            [
                'date' => $payment->date,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -100,
                'number' => 'Payment',
                'paid' => 100,
                'balance' => 0,
            ],
        ];

        unset($lines[0]['customer']);
        unset($lines[1]['invoice']);
        unset($lines[1]['customer']);
        unset($lines[2]['customer']);
        $this->assertEquals($expected, $lines);
    }

    private function verifyCreditPayment(Customer $customer, Payment $payment): void
    {
        $statement = $this->getStatement($customer);
        $result = $statement->getValues();
        unset($result['accountDetail'][0]['customer']);
        unset($result['accountDetail'][1]['customer']);
        unset($result['creditDetail'][0]['customer']);
        unset($result['unifiedDetail'][0]['customer']);
        unset($result['unifiedDetail'][1]['customer']);
        unset($result['unifiedDetail'][2]['customer']);

        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'number' => 'Previous Balance',
                'url' => null,
                'date' => $statement->start - 1,
                'amount' => 0.0,
                'balance' => 0.0,
            ],
            [
                'date' => $payment->date,
                '_type' => 'payment',
                'type' => 'Payment',
                'amount' => -100,
                'balance' => 0,
                'number' => 'Payment',
                'paid' => 100,
            ],
        ];

        $expectedCreditBalance = [
            [
                '_type' => 'adjustment',
                'description' => 'Credit',
                'type' => 'Credit',
                'date' => $payment->date,
                'issued' => 100,
                'amount' => 100,
                'creditBalance' => 100,
            ],
        ];

        $expectedUnifiedDetail = array_merge($expected, $expectedCreditBalance);

        $this->assertEquals(0, $result['balance']);
        $this->assertEquals(0, $result['previousBalance']);
        $this->assertEquals(0, $result['totalInvoiced']);
        $this->assertEquals(0, $result['totalUnapplied']);
        $this->assertEquals(0, $result['previousCreditBalance']);
        $this->assertEquals(100, $result['totalCreditsIssued']);
        $this->assertEquals(0, $result['totalCreditsSpent']);
        $this->assertEquals(100, $result['creditBalance']);
        $this->assertEquals($expected, $result['accountDetail']);
        $this->assertEquals($expectedCreditBalance, $result['creditDetail']);
        $this->assertEquals($expectedUnifiedDetail, $result['unifiedDetail']);
    }
}
