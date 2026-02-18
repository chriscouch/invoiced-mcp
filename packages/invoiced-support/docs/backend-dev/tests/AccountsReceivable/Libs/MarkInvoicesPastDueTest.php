<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Cron\ValueObjects\Run;
use App\EntryPoint\CronJob\MarkInvoicesPastDue;
use App\Tests\AppTestCase;

class MarkInvoicesPastDueTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        // create an invoice that will be past due in 1 second
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->due_date = time() - 1;
        $invoice->saveOrFail();
        self::$invoice = $invoice;

        // hack to unmark the invoice as past due
        self::getService('test.database')->update('Invoices', ['status' => InvoiceStatus::NotSent->value], ['id' => $invoice->id()]);
    }

    public function testGetCompanies(): void
    {
        $job = $this->getJob();
        $companies = $job->getCompanies();
        $this->assertTrue(in_array(self::$company->id, $companies));
    }

    public function testGetDocuments(): void
    {
        $job = $this->getJob();
        $invoices = $job->getDocuments(self::$company);

        $this->assertCount(1, $invoices);
        $this->assertInstanceOf(Invoice::class, $invoices[0]);
        $this->assertEquals(self::$invoice->id(), $invoices[0]->id());
    }

    public function testExecute(): void
    {
        $job = $this->getJob();
        $job->execute(new Run());
        $this->assertEquals(1, $job->getTaskCount());
        $this->assertEquals(InvoiceStatus::PastDue->value, self::$invoice->refresh()->status);
    }

    private function getJob(): MarkInvoicesPastDue
    {
        return self::getService('test.mark_invoices_past_due');
    }
}
