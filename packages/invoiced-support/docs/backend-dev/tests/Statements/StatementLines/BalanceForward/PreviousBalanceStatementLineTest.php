<?php

namespace App\Tests\Statements\StatementLines\BalanceForward;

use App\Statements\Libs\AbstractStatement;
use App\Statements\StatementLines\BalanceForward\PreviousBalanceStatementLine;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use App\Tests\AppTestCase;
use Mockery;

class PreviousBalanceStatementLineTest extends AppTestCase
{
    public function testApplyNoPreviousStatement(): void
    {
        $line = new PreviousBalanceStatementLine(self::getService('translator'), (int) mktime(0, 0, 0, 4, 23, 2021), null);
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(0, $totals->getPreviousBalance()->amount);
        $this->assertEquals(0, $totals->getPreviousCreditBalance()->amount);
        $this->assertEquals(0, $totals->getRunningBalance()->amount);
        $this->assertEquals(0, $totals->getRunningCreditBalance()->amount);

        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'customer' => null,
                'number' => 'Previous Balance',
                'date' => mktime(0, 0, 0, 4, 23, 2021),
                'amount' => 0.0,
                'balance' => 0.0,
                'url' => null,
            ],
        ];

        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals($expected, $totals->getUnifiedDetail());
    }

    public function testApplyPreviousStatement(): void
    {
        $previousStatement = Mockery::mock(AbstractStatement::class);
        $previousStatement->balance = 100;
        $previousStatement->creditBalance = 500;
        $line = new PreviousBalanceStatementLine(self::getService('translator'), (int) mktime(0, 0, 0, 4, 23, 2021), $previousStatement);
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(10000, $totals->getPreviousBalance()->amount);
        $this->assertEquals(10000, $totals->getRunningBalance()->amount);

        $this->assertEquals(50000, $totals->getPreviousCreditBalance()->amount);
        $this->assertEquals(50000, $totals->getRunningCreditBalance()->amount);

        $expected = [
            [
                '_type' => 'previous_balance',
                'type' => 'Previous Balance',
                'customer' => null,
                'number' => 'Previous Balance',
                'date' => mktime(0, 0, 0, 4, 23, 2021),
                'amount' => 100.0,
                'balance' => 100.0,
                'url' => null,
            ],
        ];

        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals($expected, $totals->getUnifiedDetail());
        $this->assertEquals([], $totals->getCreditDetail());
    }
}
