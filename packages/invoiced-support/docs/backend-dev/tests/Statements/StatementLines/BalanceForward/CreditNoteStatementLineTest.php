<?php

namespace App\Tests\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\Statements\StatementLines\BalanceForward\CreditNoteStatementLine;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use App\Tests\AppTestCase;

class CreditNoteStatementLineTest extends AppTestCase
{
    public function testApply(): void
    {
        $creditNote = new CreditNote();
        $customer = new Customer(['id' => -1]);
        $creditNote->setCustomer($customer);
        $creditNote->number = 'CN-00001';
        $creditNote->date = (int) mktime(0, 0, 0, 4, 23, 2021);
        $creditNote->currency = 'usd';
        $creditNote->total = 100;
        $line = new CreditNoteStatementLine($creditNote, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(-10000, $totals->getTotalInvoiced()->amount);
        $this->assertEquals(-10000, $totals->getRunningBalance()->amount);

        $expected = [
            [
                '_type' => 'credit_note',
                'type' => 'Credit Note',
                'customer' => $customer,
                'number' => 'CN-00001',
                'date' => mktime(0, 0, 0, 4, 23, 2021),
                'invoiced' => -100.0,
                'amount' => -100.0,
                'balance' => -100.0,
                'url' => null,
                'creditNote' => $creditNote,
            ],
        ];

        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals($expected, $totals->getUnifiedDetail());
        $this->assertEquals([], $totals->getCreditDetail());
    }
}
