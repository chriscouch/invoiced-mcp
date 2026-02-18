<?php

namespace App\Tests\Statements\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\CashApplication\Models\Payment;
use App\Sending\Email\Libs\DocumentEmailTemplateFactory;
use App\Statements\Libs\OpenItemStatement;
use App\Tests\AppTestCase;

class OpenItemStatementTest extends AppTestCase
{
    private static Invoice $invoice2;
    private static Payment $payment2;
    private static Payment $unappliedPayment;
    private static CreditNote $creditNote2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::$invoice->items = [['unit_cost' => 200]];
        self::$invoice->saveOrFail();
        self::hasCreditNote();
        self::$invoice->refresh();
        self::hasTransaction();

        self::$company->accounts_receivable_settings->auto_apply_credits = false;
        self::$company->accounts_receivable_settings->saveOrFail();

        self::$credit = new CreditBalanceAdjustment();
        self::$credit->setCustomer(self::$customer);
        self::$credit->date = time() + 1;
        self::$credit->amount = 10;
        self::$credit->saveOrFail();

        self::$invoice2 = new Invoice();
        self::$invoice2->setCustomer(self::$customer);
        self::$invoice2->date = time() + 2;
        self::$invoice2->items = [[
            'quantity' => 1,
            'unit_cost' => 20, ]];
        self::$invoice2->saveOrFail();

        self::$payment2 = new Payment();
        self::$payment2->setCustomer(self::$customer);
        self::$payment2->date = time() + 3;
        self::$payment2->amount = 10;
        self::$payment2->applied_to = [[
            'type' => 'applied_credit',
            'document_type' => 'invoice',
            'invoice' => self::$invoice2,
            'amount' => 10,
        ]];
        self::$payment2->saveOrFail();

        self::$unappliedPayment = new Payment();
        self::$unappliedPayment->setCustomer(self::$customer);
        self::$unappliedPayment->date = time() + 5;
        self::$unappliedPayment->amount = 70;
        self::$unappliedPayment->saveOrFail();

        $voidedInvoice = new Invoice();
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 1000]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();

        self::$creditNote2 = new CreditNote();
        self::$creditNote2->setCustomer(self::$customer);
        self::$creditNote2->date = time() + 6;
        self::$creditNote2->items = [['unit_cost' => 5]];
        self::$creditNote2->saveOrFail();
    }

    private function getStatement(Customer $customer, ?string $currency = null, ?int $date = null, bool $pastDueOnly = false): OpenItemStatement
    {
        return self::getService('test.statement_builder')->openItem($customer, $currency, $date, $pastDueOnly);
    }

    public function testDateGetters(): void
    {
        $statement = $this->getStatement(self::$customer);

        $this->assertNull($statement->getStartDate());
        $this->assertGreaterThan(0, $statement->getEndDate());
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
        $this->assertEquals(0.0, $statement->totalUnapplied);
        $this->assertEquals(0.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(0.0, $statement->totalCreditsIssued);
        $this->assertEquals(0.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);
        $this->assertEquals([], $statement->accountDetail);
        $this->assertEquals([], $statement->creditDetail);
    }

    public function testCalculate(): void
    {
        $statement = $this->getStatement(self::$customer, null, time() + 3600);

        $this->assertEquals('usd', $statement->getCurrency());

        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(15.0, $statement->totalInvoiced);
        $this->assertEquals(0.0, $statement->totalPaid);
        $this->assertEquals(0.0, $statement->totalUnapplied);
        $this->assertEquals(5.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(0.0, $statement->totalCreditsIssued);
        $this->assertEquals(0.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $accountDetail = $statement->accountDetail;
        $this->assertTrue($accountDetail[0]['invoice'] instanceof Invoice);
        $this->assertTrue($accountDetail[1]['creditNote'] instanceof CreditNote);

        unset($accountDetail[0]['invoice']);
        unset($accountDetail[1]['creditNote']);

        $expected = [
            [
                'number' => 'INV-00002',
                'url' => self::$invoice2->url,
                'date' => self::$invoice2->date,
                'dueDate' => self::$invoice2->due_date,
                'total' => 20.0,
                'balance' => 10.0,
            ],
            [
                'number' => 'CN-00002',
                'url' => self::$creditNote2->url,
                'date' => self::$creditNote2->date,
                'dueDate' => null,
                'total' => -5.0,
                'balance' => -5.0,
            ],
        ];

        unset($accountDetail[0]['customer']);
        unset($accountDetail[1]['customer']);

        $this->assertEquals($expected, $accountDetail);

        $this->assertEquals([], $statement->creditDetail);
    }

    public function testCalculatePastDueOnly(): void
    {
        $statement = $this->getStatement(self::$customer, null, time() + 3600, true);

        $this->assertEquals('usd', $statement->getCurrency());

        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(0.0, $statement->totalInvoiced);
        $this->assertEquals(0.0, $statement->totalPaid);
        $this->assertEquals(0.0, $statement->totalUnapplied);
        $this->assertEquals(0.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(0.0, $statement->totalCreditsIssued);
        $this->assertEquals(0.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $this->assertEquals([], $statement->accountDetail);

        $this->assertEquals([], $statement->creditDetail);
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

        $statement = $this->getStatement($parentCustomer, null, time() + 3600);

        $this->assertEquals('usd', $statement->getCurrency());

        $this->assertEquals(0, $statement->previousBalance);
        $this->assertEquals(100.0, $statement->totalInvoiced);
        $this->assertEquals(0.0, $statement->totalPaid);
        $this->assertEquals(0.0, $statement->totalUnapplied);
        $this->assertEquals(100.0, $statement->balance);
        $this->assertEquals(0.0, $statement->previousCreditBalance);
        $this->assertEquals(0.0, $statement->totalCreditsIssued);
        $this->assertEquals(0.0, $statement->totalCreditsSpent);
        $this->assertEquals(0.0, $statement->creditBalance);

        $accountDetail = $statement->accountDetail;
        $this->assertTrue($accountDetail[0]['invoice'] instanceof Invoice);

        unset($accountDetail[0]['invoice']);
        unset($accountDetail[0]['customer']);

        $expected = [
            [
                'number' => 'INV-00004',
                'url' => $invoice->url,
                'date' => $invoice->date,
                'dueDate' => $invoice->due_date,
                'total' => 100.0,
                'balance' => 100.0,
            ],
        ];

        $this->assertEquals($expected, $accountDetail);

        $this->assertEquals([], $statement->creditDetail);
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
}
