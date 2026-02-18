<?php

namespace App\Tests\Statements\StatementLines\BalanceForward;

use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Statements\StatementLines\BalanceForward\AppliedCreditStatementLine;
use App\Statements\StatementLines\BalanceForward\CreditBalanceAdjustmentStatementLine;
use App\Statements\StatementLines\BalanceForward\CreditNoteStatementLine;
use App\Statements\StatementLines\BalanceForward\InvoiceStatementLine;
use App\Statements\StatementLines\BalanceForward\PaymentStatementLine;
use App\Statements\StatementLines\BalanceForward\RefundStatementLine;
use App\Statements\StatementLines\BalanceForwardStatementLineFactory;
use App\Tests\AppTestCase;

class BalanceForwardStatementLineFactoryTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getFactory(): BalanceForwardStatementLineFactory
    {
        return new BalanceForwardStatementLineFactory(self::getService('translator'));
    }

    public function testMakeInvoice(): void
    {
        $factory = $this->getFactory();
        $this->assertInstanceOf(InvoiceStatementLine::class, $factory->make(new Invoice()));
    }

    public function testMakeCreditNote(): void
    {
        $factory = $this->getFactory();
        $this->assertInstanceOf(CreditNoteStatementLine::class, $factory->make(new CreditNote()));
    }

    public function testMakeTransactionPayment(): void
    {
        $factory = $this->getFactory();
        $transaction = new Transaction([
            'type' => Transaction::TYPE_PAYMENT,
            'currency' => 'usd',
            'amount' => 100,
        ]);
        $this->assertInstanceOf(PaymentStatementLine::class, $factory->make($transaction));

        $transaction = new Transaction([
            'type' => Transaction::TYPE_PAYMENT,
            'parent_transaction' => 1234,
            'currency' => 'usd',
            'amount' => 100,
        ]);
        $this->assertNull($factory->make($transaction));
    }

    public function testMakeTransactionCharge(): void
    {
        $factory = $this->getFactory();
        $transaction = new Transaction([
            'type' => Transaction::TYPE_CHARGE,
            'currency' => 'usd',
            'amount' => 100,
        ]);
        $this->assertInstanceOf(PaymentStatementLine::class, $factory->make($transaction));

        $transaction = new Transaction([
            'type' => Transaction::TYPE_CHARGE,
            'parent_transaction' => 1234,
            'currency' => 'usd',
            'amount' => 100,
        ]);
        $this->assertNull($factory->make($transaction));
    }

    public function testMakeTransactionAppliedCreditNote(): void
    {
        $factory = $this->getFactory();
        $payment = new Payment([
            'currency' => 'usd',
            'amount' => 100,
        ]);
        $transaction = new Transaction([
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::OTHER,
            'currency' => 'usd',
            'amount' => 50,
            'payment' => $payment,
        ]);
        $this->assertInstanceOf(PaymentStatementLine::class, $factory->make($transaction));

        $transaction = new Transaction([
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::OTHER,
            'parent_transaction' => 1234,
            'currency' => 'usd',
            'amount' => 50,
            'payment' => $payment,
        ]);
        $this->assertNull($factory->make($transaction));

        $payment = new Payment([
            'currency' => 'usd',
            'amount' => 0,
        ]);
        $transaction = new Transaction([
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::OTHER,
            'currency' => 'usd',
            'amount' => 50,
            'payment' => $payment,
        ]);
        $this->assertNull($factory->make($transaction));

        $transaction = new Transaction([
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::OTHER,
            'currency' => 'usd',
            'amount' => 100,
        ]);
        $this->assertNull($factory->make($transaction));
    }

    public function testMakeTransactionAppliedCredit(): void
    {
        $factory = $this->getFactory();
        $transaction = new Transaction([
            'type' => Transaction::TYPE_CHARGE,
            'method' => PaymentMethod::BALANCE,
            'currency' => 'usd',
            'amount' => 100,
        ]);
        $this->assertInstanceOf(AppliedCreditStatementLine::class, $factory->make($transaction));
    }

    public function testMakeTransactionCreditBalanceAdjustment(): void
    {
        $factory = $this->getFactory();
        $transaction = new Transaction([
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::BALANCE,
            'currency' => 'usd',
            'amount' => 100,
        ]);
        $this->assertInstanceOf(CreditBalanceAdjustmentStatementLine::class, $factory->make($transaction));
    }

    public function testMakeTransactionRefund(): void
    {
        $factory = $this->getFactory();
        $transaction = new Transaction([
            'type' => Transaction::TYPE_REFUND,
            'currency' => 'usd',
            'amount' => 50,
        ]);
        $this->assertInstanceOf(RefundStatementLine::class, $factory->make($transaction));
    }
}
