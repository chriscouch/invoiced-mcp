<?php

namespace App\Tests\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\Transaction;
use App\Statements\StatementLines\BalanceForward\RefundStatementLine;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use App\Tests\AppTestCase;

class RefundStatementLineTest extends AppTestCase
{
    public function testApply(): void
    {
        $refund = new Transaction();
        $refund->currency = 'usd';
        $refund->amount = 100;
        $refund->date = (int) mktime(0, 0, 0, 4, 23, 2021);
        $customer = new Customer(['id' => -1]);
        $refund->setCustomer($customer);
        $line = new RefundStatementLine($refund, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(-10000, $totals->getTotalPaid()->amount);
        $this->assertEquals(10000, $totals->getRunningBalance()->amount);

        $expected = [
            [
                '_type' => 'refund',
                'type' => 'Refund',
                'customer' => $customer,
                'number' => null,
                'date' => mktime(0, 0, 0, 4, 23, 2021),
                'paid' => -100.0,
                'amount' => 100.0,
                'balance' => 100.0,
            ],
        ];

        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals($expected, $totals->getUnifiedDetail());
        $this->assertEquals([], $totals->getCreditDetail());
    }
}
