<?php

namespace App\Tests\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Statements\StatementLines\BalanceForward\InvoiceStatementLine;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use App\Tests\AppTestCase;

class InvoiceStatementLineTest extends AppTestCase
{
    public function testApply(): void
    {
        $invoice = new Invoice();
        $customer = new Customer(['id' => -1]);
        $invoice->setCustomer($customer);
        $invoice->number = 'INV-00001';
        $invoice->date = (int) mktime(0, 0, 0, 4, 23, 2021);
        $invoice->currency = 'usd';
        $invoice->total = 100;
        $line = new InvoiceStatementLine($invoice, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(10000, $totals->getTotalInvoiced()->amount);
        $this->assertEquals(10000, $totals->getRunningBalance()->amount);

        $expected = [
            [
                '_type' => 'invoice',
                'type' => 'Invoice',
                'customer' => $customer,
                'number' => 'INV-00001',
                'date' => mktime(0, 0, 0, 4, 23, 2021),
                'invoiced' => 100.0,
                'amount' => 100.0,
                'balance' => 100.0,
                'url' => null,
                'invoice' => $invoice,
            ],
        ];

        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals($expected, $totals->getUnifiedDetail());
        $this->assertEquals([], $totals->getCreditDetail());
    }
}
