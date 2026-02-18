<?php

namespace App\Tests\CashApplication\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Libs\TransactionNormalizer;
use App\CashApplication\Models\Payment;
use App\CashApplication\TransactionBuilder\AppliedCreditSplit;
use App\CashApplication\TransactionBuilder\ConvenienceFeeSplit;
use App\CashApplication\TransactionBuilder\CreditNoteSplit;
use App\CashApplication\TransactionBuilder\CreditSplit;
use App\CashApplication\TransactionBuilder\DocumentAdjustmentSplit;
use App\CashApplication\TransactionBuilder\EstimateSplit;
use App\CashApplication\TransactionBuilder\InvoiceSplit;
use App\Tests\AppTestCase;

class TransactionNormalizerTest extends AppTestCase
{
    private static TransactionNormalizer $normalizer;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();

        self::$normalizer = new TransactionNormalizer();
    }

    public function testAppliedCreditSplit(): void
    {
        $invoice = $this->createDocument(self::$customer, new Invoice());
        $payment = $this->createPayment(self::$customer, []);
        [$transaction] = (new AppliedCreditSplit())->build($payment, [
            'amount' => 50,
            'document_type' => 'invoice',
            'invoice' => $invoice->id(),
        ]);

        $expected = [
            'id' => null,
            'type' => PaymentItemType::AppliedCredit->value,
            'amount' => 50,
            'document_type' => 'invoice',
            'invoice' => (int) $invoice->id(),
        ];

        $this->assertEquals($expected, self::$normalizer->normalize($transaction));
    }

    public function testDocumentAdjustmentSplit(): void
    {
        // invoice
        $invoice = $this->createDocument(self::$customer, new Invoice());
        $payment = $this->createPayment(self::$customer, []);
        [$transaction] = (new DocumentAdjustmentSplit())->build($payment, [
            'amount' => 50,
            'document_type' => 'invoice',
            'invoice' => $invoice->id(),
        ]);

        $expected = [
            'id' => null,
            'type' => PaymentItemType::DocumentAdjustment->value,
            'amount' => 50,
            'document_type' => 'invoice',
            'invoice' => (int) $invoice->id(),
        ];

        $this->assertEquals($expected, self::$normalizer->normalize($transaction));

        // credit note
        $creditNote = $this->createDocument(self::$customer, new CreditNote());
        $payment = $this->createPayment(self::$customer, []);
        [$transaction] = (new DocumentAdjustmentSplit())->build($payment, [
            'amount' => 50,
            'document_type' => 'credit_note',
            'credit_note' => $creditNote->id(),
        ]);

        $expected = [
            'id' => null,
            'type' => PaymentItemType::DocumentAdjustment->value,
            'amount' => -50,
            'document_type' => 'credit_note',
            'credit_note' => (int) $creditNote->id(),
        ];

        $this->assertEquals($expected, self::$normalizer->normalize($transaction));
    }

    public function testCreditSplit(): void
    {
        $payment = $this->createPayment(self::$customer, []);
        [$transaction] = (new CreditSplit())->build($payment, [
            'amount' => 50,
        ]);

        $expected = [
            'id' => null,
            'type' => PaymentItemType::Credit->value,
            'amount' => 50,
        ];

        $this->assertEquals($expected, self::$normalizer->normalize($transaction));
    }

    public function testCreditNoteSplit(): void
    {
        $invoice = $this->createDocument(self::$customer, new Invoice());
        $creditNote = $this->createDocument(self::$customer, new CreditNote());
        $payment = $this->createPayment(self::$customer, []);
        [$transaction] = (new CreditNoteSplit())->build($payment, [
            'amount' => 50,
            'credit_note' => $creditNote->id(),
            'document_type' => 'invoice',
            'invoice' => $invoice->id(),
        ]);

        $expected = [
            'id' => null,
            'type' => PaymentItemType::CreditNote->value,
            'amount' => 50,
            'credit_note' => (int) $creditNote->id(),
            'document_type' => 'invoice',
            'invoice' => (int) $invoice->id(),
        ];

        $this->assertEquals($expected, self::$normalizer->normalize($transaction));
    }

    public function testConvenienceFeeSplit(): void
    {
        $payment = $this->createPayment(self::$customer, []);
        [$transaction] = (new ConvenienceFeeSplit())->build($payment, [
            'amount' => 50,
        ]);

        $expected = [
            'id' => null,
            'type' => PaymentItemType::ConvenienceFee->value,
            'amount' => 50,
        ];

        $this->assertEquals($expected, self::$normalizer->normalize($transaction));
    }

    public function testEstimateSplit(): void
    {
        $estimate = $this->createDocument(self::$customer, new Estimate());
        $payment = $this->createPayment(self::$customer, []);
        [$transaction] = (new EstimateSplit())->build($payment, [
            'amount' => 50,
            'estimate' => $estimate->id(),
        ]);

        $expected = [
            'id' => null,
            'type' => PaymentItemType::Estimate->value,
            'amount' => 50,
            'estimate' => (int) $estimate->id(),
        ];

        $this->assertEquals($expected, self::$normalizer->normalize($transaction));
    }

    public function testInvoiceSplit(): void
    {
        $invoice = $this->createDocument(self::$customer, new Invoice());
        $payment = $this->createPayment(self::$customer, []);
        [$transaction] = (new InvoiceSplit())->build($payment, [
            'amount' => 50,
            'invoice' => $invoice->id(),
        ]);

        $expected = [
            'id' => null,
            'type' => PaymentItemType::Invoice->value,
            'amount' => 50,
            'invoice' => (int) $invoice->id(),
        ];

        $this->assertEquals($expected, self::$normalizer->normalize($transaction));
    }

    //
    // Helpers
    //

    private function createDocument(Customer $customer, ReceivableDocument $document): ReceivableDocument
    {
        $document->setCustomer($customer);
        $document->items = [
            [
                'unit_cost' => 100,
            ],
        ];
        $document->saveOrFail();

        return $document;
    }

    private function createPayment(Customer $customer, array $appliedTo): Payment
    {
        $payment = new Payment();
        $payment->setCustomer($customer);
        $payment->applied_to = $appliedTo;
        $payment->saveOrFail();

        return $payment;
    }
}
