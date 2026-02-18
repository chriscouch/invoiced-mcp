<?php

namespace App\Tests\Statements\StatementLines\OpenItem;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\StatementLines\OpenItem\OpenInvoiceStatementLine;
use App\Tests\AppTestCase;

class InvoiceStatementLineTest extends AppTestCase
{
    private function getInvoice(): Invoice
    {
        $invoice = new Invoice([
            'currency' => 'usd',
            'number' => 'INV-00001',
            'total' => 100.0,
            'balance' => 20.0,
            'date' => mktime(0, 0, 0, 5, 4, 2021),
            'due_date' => mktime(0, 0, 0, 6, 4, 2021),
        ]);
        $customer = new Customer(['id' => -1]);
        $invoice->setCustomer($customer);

        return $invoice;
    }

    public function testGetLineTotal(): void
    {
        $invoice = $this->getInvoice();
        $line = new OpenInvoiceStatementLine($invoice);

        $this->assertEquals(new Money('usd', 10000), $line->getLineTotal());
    }

    public function testGetLineBalance(): void
    {
        $invoice = $this->getInvoice();
        $line = new OpenInvoiceStatementLine($invoice);

        $this->assertEquals(new Money('usd', 2000), $line->getLineBalance());
    }

    public function testGetDate(): void
    {
        $invoice = $this->getInvoice();
        $line = new OpenInvoiceStatementLine($invoice);

        $this->assertEquals((int) mktime(0, 0, 0, 5, 4, 2021), $line->getDate());
    }

    public function testBuild(): void
    {
        $invoice = $this->getInvoice();
        $line = new OpenInvoiceStatementLine($invoice);

        $this->assertEquals([
            'invoice' => $invoice,
            'customer' => $invoice->customer(),
            'number' => 'INV-00001',
            'url' => null,
            'date' => 1620086400,
            'dueDate' => 1622764800,
            'total' => 100.0,
            'balance' => 20.0,
        ], $line->build());
    }
}
