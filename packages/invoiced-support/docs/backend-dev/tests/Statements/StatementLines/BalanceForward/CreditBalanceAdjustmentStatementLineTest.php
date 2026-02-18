<?php

namespace App\Tests\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Models\Card;
use App\Statements\StatementLines\BalanceForward\CreditBalanceAdjustmentStatementLine;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use App\Tests\AppTestCase;

class CreditBalanceAdjustmentStatementLineTest extends AppTestCase
{
    public function testApply(): void
    {
        $transaction = new Transaction();
        $transaction->currency = 'usd';
        $transaction->amount = 10000;
        $customer = new Customer(['id' => -1]);
        $transaction->setCustomer($customer);
        $line = new CreditBalanceAdjustmentStatementLine($transaction, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertTrue($totals->getRunningBalance()->isZero());
        $this->assertEquals(-10000, $totals->getTotalPaid()->toDecimal());
    }

    public function testApplyParentTransaction(): void
    {
        $transaction = new Transaction();
        $transaction->currency = 'usd';
        $transaction->amount = 10000;
        $transaction->parent_transaction = 1;
        $customer = new Customer(['id' => -1]);
        $transaction->setCustomer($customer);
        $line = new CreditBalanceAdjustmentStatementLine($transaction, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(-10000, $totals->getRunningBalance()->toDecimal());
        $this->assertTrue($totals->getTotalPaid()->isZero());
    }

    public function testApplyCreditNote(): void
    {
        $transaction = new Transaction();
        $transaction->currency = 'usd';
        $transaction->amount = 10000;
        $transaction->parent_transaction = null;
        $transaction->credit_note = 1;
        $customer = new Customer(['id' => -1]);
        $transaction->setCustomer($customer);
        $line = new CreditBalanceAdjustmentStatementLine($transaction, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(-10000, $totals->getRunningBalance()->toDecimal());
        $this->assertTrue($totals->getTotalPaid()->isZero());
    }

    public function testApplyCredit(): void
    {
        $transaction = new Transaction();
        $transaction->currency = 'usd';
        $transaction->amount = 10000;
        $transaction->payment = new Payment();
        $customer = new Customer(['id' => -1]);
        $transaction->setCustomer($customer);

        $expected = [
            [
                '_type' => 'payment',
                'type' => 'Payment',
                'customer' => $customer,
                'number' => 'Payment',
                'date' => 'now',
                'paid' => -10000.0,
                'amount' => 10000.0,
                'balance' => 0.0,
            ],
        ];
        $expectedCredit = [
            [
                '_type' => 'adjustment',
                'description' => 'Adjustment',
                'customer' => $customer,
                'type' => 'Adjustment',
                'date' => 'now',
                'issued' => -10000.0,
                'amount' => -10000.0,
                'creditBalance' => -10000.0,
            ],
        ];

        $line = new CreditBalanceAdjustmentStatementLine($transaction, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');
        $line->apply($totals);
        $this->assertTrue($totals->getRunningBalance()->isZero());
        $this->assertEquals(-10000, $totals->getTotalPaid()->toDecimal());
        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals(array_merge($expected, $expectedCredit), $totals->getUnifiedDetail());

        $transaction->gateway_id = 'test';
        $line = new CreditBalanceAdjustmentStatementLine($transaction, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');
        $expected[0]['number'] = $transaction->gateway_id;
        $line->apply($totals);
        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals(array_merge($expected, $expectedCredit), $totals->getUnifiedDetail());

        $card = new Card();
        $card->brand = 'Visa';
        $card->last4 = '0000';
        $transaction->setPaymentSource($card);
        $line = new CreditBalanceAdjustmentStatementLine($transaction, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');
        $expected[0]['number'] = 'Visa *0000';
        $line->apply($totals);
        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals(array_merge($expected, $expectedCredit), $totals->getUnifiedDetail());

        $transaction->credit_note = 1;
        $line = new CreditBalanceAdjustmentStatementLine($transaction, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');
        $line->apply($totals);
        $this->assertEquals(-10000, $totals->getRunningBalance()->toDecimal());
        $this->assertTrue($totals->getTotalPaid()->isZero());
    }
}
