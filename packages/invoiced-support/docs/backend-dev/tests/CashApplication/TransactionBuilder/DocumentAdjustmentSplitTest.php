<?php

namespace App\Tests\CashApplication\TransactionBuilder;

use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Tests\AppTestCase;

class DocumentAdjustmentSplitTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testCreditBalanceHistory(): void
    {
        $balanceGenerator = new CustomerBalanceGenerator(self::getService('test.database'));

        // invoice adjustment
        self::hasInvoice(); // starting balance of 100
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => PaymentItemType::DocumentAdjustment->value,
                'amount' => 50,
                'document_type' => 'invoice',
                'invoice' => self::$invoice->id(),
            ],
        ];
        $payment->saveOrFail();

        $expectedBalance = [
            'available_credits' => 0,
            'currency' => self::$company->currency,
            'history' => [],
            'past_due' => false,
            'total_outstanding' => 50,
            'due_now' => 50,
            'open_credit_notes' => 0,
        ];
        $this->assertEquals($expectedBalance, $balanceGenerator->generate(self::$customer)->toArray());

        // credit note adjustment
        self::hasUnappliedCreditNote();  // starting balance of 100
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => PaymentItemType::DocumentAdjustment->value,
                'amount' => 50,
                'document_type' => 'credit_note',
                'credit_note' => self::$creditNote->id(),
            ],
        ];
        $payment->saveOrFail();

        $expectedBalance = [
            'available_credits' => 0,
            'currency' => self::$company->currency,
            'history' => [],
            'past_due' => false,
            'total_outstanding' => 50,
            'due_now' => 50,
            'open_credit_notes' => 50,
        ];
        $this->assertEquals($expectedBalance, $balanceGenerator->generate(self::$customer)->toArray());

        // estimate adjustment
        self::hasEstimate();
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => PaymentItemType::DocumentAdjustment->value,
                'amount' => 50,
                'document_type' => 'estimate',
                'estimate' => self::$estimate->id(),
            ],
        ];
        $payment->saveOrFail();

        $expectedBalance = [
            'available_credits' => 0,
            'currency' => self::$company->currency,
            'history' => [],
            'past_due' => false,
            'total_outstanding' => 50,
            'due_now' => 50,
            'open_credit_notes' => 50,
        ];
        $this->assertEquals($expectedBalance, $balanceGenerator->generate(self::$customer)->toArray());
    }

    public function testInvoice(): void
    {
        self::hasInvoice();
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => PaymentItemType::DocumentAdjustment->value,
                'amount' => 40,
                'document_type' => 'invoice',
                'invoice' => self::$invoice->id(),
            ],
        ];
        $payment->saveOrFail();

        $this->assertEquals(60, self::$invoice->refresh()->balance);
    }

    public function testCreditNote(): void
    {
        self::hasUnappliedCreditNote();
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => PaymentItemType::DocumentAdjustment->value,
                'amount' => 20,
                'document_type' => 'credit_note',
                'credit_note' => self::$creditNote->id(),
            ],
        ];
        $payment->saveOrFail();

        $this->assertEquals(80, self::$creditNote->refresh()->balance);
    }
}
