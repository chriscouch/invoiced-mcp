<?php

namespace App\Tests\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\Transaction;
use App\Statements\StatementLines\BalanceForward\AppliedCreditStatementLine;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use App\Tests\AppTestCase;

class AppliedCreditStatementLineTest extends AppTestCase
{
    public function testApply(): void
    {
        $appliedCredit = new Transaction();
        $customer = new Customer(['id' => -1]);
        $appliedCredit->setCustomer($customer);
        $appliedCredit->currency = 'usd';
        $appliedCredit->amount = 100;
        $appliedCredit->date = (int) mktime(0, 0, 0, 4, 23, 2021);
        $line = new AppliedCreditStatementLine($appliedCredit, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(0, $totals->getTotalPaid()->amount);
        $this->assertEquals(-10000, $totals->getRunningBalance()->amount);
        $this->assertEquals(0, $totals->getTotalCreditsIssued()->amount);
        $this->assertEquals(10000, $totals->getTotalCreditsSpent()->amount);
        $this->assertEquals(-10000, $totals->getRunningCreditBalance()->amount);

        $expected = [
            [
                '_type' => 'adjustment',
                'type' => 'Applied Credit',
                'customer' => $customer,
                'number' => 'Applied Credit',
                'description' => 'Applied Credit',
                'date' => mktime(0, 0, 0, 4, 23, 2021),
                'amount' => -100.0,
                'balance' => -100.0,
                'charged' => 100.0,
                'creditBalance' => -100.0,
                'url' => null,
            ],
        ];

        $this->assertEquals([], $totals->getAccountDetail());
        $this->assertEquals($expected, $totals->getUnifiedDetail());
        $this->assertEquals($expected, $totals->getCreditDetail());
    }
}
