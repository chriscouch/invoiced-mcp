<?php

namespace App\Tests\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Statements\StatementLines\BalanceForward\PaymentStatementLine;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use App\Tests\AppTestCase;

class PaymentStatementLineTest extends AppTestCase
{
    public function testApply(): void
    {
        $paymentTxn = new Transaction();
        $paymentTxn->currency = 'usd';
        $paymentTxn->amount = 100;
        $paymentTxn->date = (int) mktime(0, 0, 0, 4, 23, 2021);
        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->amount = 100;
        $paymentTxn->payment = $payment;
        $customer = new Customer(['id' => -1]);
        $paymentTxn->setCustomer($customer);
        $line = new PaymentStatementLine($paymentTxn, self::getService('translator'));
        $totals = new BalanceForwardStatementTotals('usd');

        $line->apply($totals);

        $this->assertEquals(10000, $totals->getTotalPaid()->amount);
        $this->assertEquals(-10000, $totals->getRunningBalance()->amount);

        $expected = [
            [
                '_type' => 'payment',
                'type' => 'Payment',
                'customer' => $customer,
                'number' => 'Payment',
                'date' => mktime(0, 0, 0, 4, 23, 2021),
                'paid' => 100.0,
                'amount' => -100.0,
                'balance' => -100.0,
            ],
        ];

        $this->assertEquals($expected, $totals->getAccountDetail());
        $this->assertEquals($expected, $totals->getUnifiedDetail());
        $this->assertEquals([], $totals->getCreditDetail());
    }
}
