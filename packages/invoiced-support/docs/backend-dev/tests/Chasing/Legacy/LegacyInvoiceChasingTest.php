<?php

namespace App\Tests\Chasing\Legacy;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Legacy\InvoiceChaser;
use App\Chasing\Legacy\InvoiceChasingScheduler;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Orm\Query;
use App\EntryPoint\CronJob\CalculateInvoiceChaseTimes;
use App\EntryPoint\CronJob\ChaseInvoicesLegacy;
use App\PaymentProcessing\Models\Card;
use App\Tests\AppTestCase;

class LegacyInvoiceChasingTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$company->features->enable('legacy_chasing');
        self::hasCustomer();

        self::$invoice = new Invoice();
        self::$invoice->setCustomer(self::$customer);
        self::$invoice->date = time();
        self::$invoice->due_date = time() - 3600;
        self::$invoice->chase = true;
        self::$invoice->items = [
            [
                'quantity' => 1,
                'unit_cost' => 1000,
            ],
        ];
        self::$invoice->save();

        $voidedInvoice = new Invoice();
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 1000]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();
    }

    private function getChaser(): InvoiceChaser
    {
        return new InvoiceChaser(self::getService('test.email_spool'));
    }

    private function getScheduler(): InvoiceChasingScheduler
    {
        return new InvoiceChasingScheduler();
    }

    public function getChaseJob(): ChaseInvoicesLegacy
    {
        return self::getService('test.chase_invoices');
    }

    public function getCalculateJob(): CalculateInvoiceChaseTimes
    {
        return self::getService('test.calculate_invoice_chase_times');
    }

    private function getInvoice(): Invoice
    {
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $customer = new Customer();
        $invoice->customer = -1;
        $invoice->setRelation('customer', $customer);

        return $invoice;
    }

    public function testCalculateNextChase(): void
    {
        // should be chase according to the following schedule:
        //   5 days before due date
        //   2 days after due date
        //   9 days after due date
        //   ...

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $company = $invoice->tenant();
        $customer = new Customer();
        $invoice->customer = -1;
        $invoice->setRelation('customer', $customer);

        $company->accounts_receivable_settings->chase_schedule = [-5, '~3'];
        $company->accounts_receivable_settings->allow_chasing = true;
        $this->assertTrue($company->accounts_receivable_settings->save());

        $invoice->last_sent = null;
        $invoice->due_date = 1000000;
        $invoice->closed = false;
        $invoice->chase = true;
        $invoice->paid = false;
        $invoice->draft = false;

        // try with company that does not allow chasing
        $company->accounts_receivable_settings->allow_chasing = false;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));

        // try with paid invoice
        $company->accounts_receivable_settings->allow_chasing = true;
        $invoice->paid = true;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));

        // try with pending invoice
        $invoice->status = InvoiceStatus::Pending->value;
        $invoice->paid = false;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));

        // try with draft
        $invoice->status = InvoiceStatus::Draft->value;
        $invoice->draft = true;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));

        // try with closed invoice
        $invoice->draft = false;
        $invoice->closed = true;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));

        // try with voided invoice
        $invoice->closed = false;
        $invoice->voided = true;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));

        // try with chasing disabled
        $invoice->voided = false;
        $invoice->chase = false;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));

        // try with no due date
        $invoice->chase = true;
        $invoice->due_date = null;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));

        // should not work because it has already been chased recently
        $invoice->due_date = 1000000;
        $invoice->last_sent = null;
        $this->assertEquals([1000000 - 5 * 86400 + 3600, 'email'], $this->getScheduler()->calculateNextChase($invoice));

        // testing fixed component
        $invoice->last_sent = 1000000 - 6 * 86400;
        $this->assertEquals([1000000 - 5 * 86400 + 3600, 'email'], $this->getScheduler()->calculateNextChase($invoice));

        // test repeating component
        $invoice->last_sent = 1000000 - 5 * 86400;
        $this->assertEquals([1000000 - 2 * 86400 + 3600, 'email'], $this->getScheduler()->calculateNextChase($invoice));

        // try on issue
        $company->accounts_receivable_settings->chase_schedule = ['issued'];
        $company->accounts_receivable_settings->allow_chasing = true;
        $this->assertTrue($company->accounts_receivable_settings->save());

        $invoice->chase = true;
        $invoice->date = time();
        $invoice->due_date = null;
        $invoice->last_sent = null;
        $this->assertEquals([$invoice->date + 3600, 'email'], $this->getScheduler()->calculateNextChase($invoice));

        // should not allow chasing when customer chasing is disabled
        $customer->chase = false;
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));
    }

    public function testCalculateNextChaseAutoPayPastDue(): void
    {
        $invoice = $this->getInvoice();
        $company = $invoice->tenant();

        $company->accounts_receivable_settings->chase_schedule = [0];
        $company->accounts_receivable_settings->allow_chasing = true;
        $this->assertTrue($company->accounts_receivable_settings->save());

        $invoice->date = 100;
        $invoice->due_date = 1000000;
        $invoice->closed = false;
        $invoice->chase = true;
        $invoice->paid = false;
        $invoice->draft = false;
        $invoice->status = InvoiceStatus::PastDue->value;
        $invoice->autopay = true;

        $this->assertEquals([$invoice->due_date + 3600, 'email'], $this->getScheduler()->calculateNextChase($invoice));
    }

    public function testCalculateNextChaseAutoPayNoPaymentInfo(): void
    {
        $invoice = $this->getInvoice();
        $company = $invoice->tenant();

        $company->accounts_receivable_settings->chase_schedule = [0];
        $company->accounts_receivable_settings->allow_chasing = true;
        $this->assertTrue($company->accounts_receivable_settings->save());

        $invoice->date = 100;
        $invoice->due_date = 1000000;
        $invoice->closed = false;
        $invoice->chase = true;
        $invoice->paid = false;
        $invoice->draft = false;
        $invoice->status = InvoiceStatus::NotSent->value;
        $invoice->autopay = true;

        $this->assertEquals([$invoice->due_date + 3600, 'email'], $this->getScheduler()->calculateNextChase($invoice));

        // add a payment source
        $customer = $invoice->customer();
        $card = new Card();
        $customer->setPaymentSource($card);
        $this->assertEquals([null, null], $this->getScheduler()->calculateNextChase($invoice));
    }

    public function testCanChaseEmail(): void
    {
        $this->assertTrue($this->getChaser()->canChaseEmail(self::$invoice));

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->customer = (int) self::$customer->id();
        $customer = $invoice->customer();

        // try with invalid email
        $customer->email = null;
        $this->assertTrue($customer->save());
        $this->assertFalse($this->getChaser()->canChaseEmail($invoice));

        // should work now
        $customer->email = 'sherlock@example.com';
        $this->assertTrue($customer->save());

        $this->assertTrue($this->getChaser()->canChaseEmail($invoice));

        // try with chasing disabled
        $customer->chase = false;

        $this->assertFalse($this->getChaser()->canChaseEmail($invoice));
    }

    public function testGetInvoices(): void
    {
        self::$invoice->recalculate_chase = false;
        self::$invoice->saveOrFail();

        $query = $this->getChaser()->getInvoicesQuery(self::$company);

        $this->assertInstanceOf(Query::class, $query);

        $ids = [];
        foreach ($query->first(1000) as $invoice) {
            $ids[] = $invoice->id();
        }

        $this->assertEquals([], $ids);

        // set next_chase_on in the past
        self::$invoice->next_chase_on = strtotime('-1 hour');
        self::$invoice->saveOrFail();
        $query = $this->getChaser()->getInvoicesQuery(self::$company);

        $ids = [];
        foreach ($query->first(1000) as $invoice) {
            $ids[] = $invoice->id();
        }

        $this->assertEquals([self::$invoice->id()], $ids);
    }

    /**
     * @depends testGetInvoices
     */
    public function testCalculate(): void
    {
        // should set the invoice recalculate_chase to true
        self::$invoice->recalculate_chase = false;
        self::$invoice->saveOrFail();

        self::$company->accounts_receivable_settings->chase_schedule = [-5];
        self::$company->accounts_receivable_settings->allow_chasing = true;
        self::$company->accounts_receivable_settings->saveOrFail();
        $this->assertTrue(self::$invoice->clearCache()->recalculate_chase);

        self::$invoice->due_date = 1000000;
        self::$invoice->last_sent = 1000000 - 6 * 86400;
        self::$invoice->saveOrFail();

        $job = $this->getCalculateJob();
        $job->execute(new Run());

        $this->assertFalse(self::$invoice->refresh()->recalculate_chase);
        $this->assertEquals(1000000 - 5 * 86400 + 3600, self::$invoice->next_chase_on);
    }

    /**
     * @depends testCalculate
     */
    public function testRun(): void
    {
        $job = $this->getChaseJob();
        $job->execute(new Run());
        $this->assertEquals(1, $job->getTaskCount());

        $this->assertNull(self::$invoice->refresh()->next_chase_on);
    }

    public function testChaseInvoiceEmail(): void
    {
        self::getService('test.tenant')->set(self::$company);

        self::$company->accounts_receivable_settings->chase_schedule = [-5, 0, ['step' => 3, 'action' => 'flag']];
        self::$company->accounts_receivable_settings->allow_chasing = true;
        self::$company->accounts_receivable_settings->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->due_date = time() - 10;
        $invoice->chase = true;
        $invoice->next_chase_on = time() - 10;
        $invoice->items = [['unit_cost' => 1000]];
        $invoice->saveOrFail();

        $this->assertTrue($this->getChaser()->chaseInvoice($invoice, 'email'));
        self::getService('test.email_spool')->flush();

        // expected next chase time is in 3 days, 1 hour
        $expectedNext = strtotime('+3 days', $invoice->due_date) + 3600;
        $this->assertEquals('flag', $invoice->next_chase_step);
        $this->assertEquals(date('c', $expectedNext), date('c', $invoice->next_chase_on));
    }

    public function testChaseInvoiceFlagStep(): void
    {
        self::getService('test.tenant')->set(self::$company);

        self::$company->accounts_receivable_settings->chase_schedule = [-5, 0, ['step' => 3, 'action' => 'flag']];
        self::$company->accounts_receivable_settings->allow_chasing = true;
        self::$company->accounts_receivable_settings->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->due_date = strtotime('-3 days') - 10;
        $invoice->chase = true;
        $invoice->next_chase_on = time() - 10;
        $invoice->items = [['unit_cost' => 1000]];
        $invoice->saveOrFail();

        $this->assertTrue($this->getChaser()->chaseInvoice($invoice, 'flag'));
        self::getService('test.email_spool')->flush();

        $this->assertTrue($invoice->needs_attention);
        // should be no more chasing
        $this->assertNull($invoice->next_chase_on);
        $this->assertNull($invoice->next_chase_step);
    }
}
