<?php

namespace App\Tests\CashApplication\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\ValueObjects\CreditNoteStatus;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Libs\ApplyPayment;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use stdClass;

class ApplyPaymentTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function setUp(): void
    {
        parent::setUp();
        self::hasCustomer();
        self::hasInvoice();
        self::hasPayment();
        self::hasEstimate();
    }

    public function testApply(): void
    {
        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [['unit_cost' => 105]];
        $invoice2->saveOrFail();

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => 90,
            ],
            [
                'type' => PaymentItemType::Credit->value,
                'amount' => 5,
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice2->id(),
                'amount' => 105,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);

        $transactions = Transaction::queryWithTenant(self::$company)
            ->where('payment_id', self::$payment)
            ->all();

        $this->assertCount(3, $transactions);
        $expected = [
            'id' => $transactions[0]->id(),
            'object' => 'transaction',
            'invoice' => self::$invoice->id(),
            'credit_note' => null,
            'customer' => self::$customer->id(),
            'date' => self::$payment->date,
            'type' => Transaction::TYPE_PAYMENT,
            'method' => PaymentMethod::OTHER,
            'gateway' => null,
            'gateway_id' => null,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'usd',
            'amount' => 90,
            'notes' => null,
            'parent_transaction' => null,
            'pdf_url' => self::$payment->pdf_url,
            'metadata' => new stdClass(),
            'created_at' => $transactions[0]->created_at,
            'updated_at' => $transactions[0]->updated_at,
            'estimate' => null,
            'payment_id' => self::$payment->id(),
        ];
        $this->assertEquals($expected, $transactions[0]->toArray());
        $expected = [
            'id' => $transactions[1]->id(),
            'object' => 'transaction',
            'invoice' => null,
            'credit_note' => null,
            'customer' => self::$customer->id(),
            'date' => self::$payment->date,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::BALANCE,
            'gateway' => null,
            'gateway_id' => null,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'usd',
            'amount' => -5,
            'notes' => 'Overpayment',
            'parent_transaction' => $transactions[0]->id(),
            'pdf_url' => self::$payment->pdf_url,
            'metadata' => new stdClass(),
            'created_at' => $transactions[1]->created_at,
            'updated_at' => $transactions[1]->updated_at,
            'estimate' => null,
            'payment_id' => self::$payment->id(),
        ];
        $this->assertEquals($expected, $transactions[1]->toArray());
        $expected = [
            'id' => $transactions[2]->id(),
            'object' => 'transaction',
            'invoice' => $invoice2->id(),
            'credit_note' => null,
            'customer' => self::$customer->id(),
            'date' => self::$payment->date,
            'type' => Transaction::TYPE_PAYMENT,
            'method' => PaymentMethod::OTHER,
            'gateway' => null,
            'gateway_id' => null,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'usd',
            'amount' => 105,
            'notes' => null,
            'parent_transaction' => $transactions[0]->id(),
            'pdf_url' => self::$payment->pdf_url,
            'metadata' => new stdClass(),
            'created_at' => $transactions[2]->created_at,
            'updated_at' => $transactions[2]->updated_at,
            'estimate' => null,
            'payment_id' => self::$payment->id(),
        ];
        $this->assertEquals($expected, $transactions[2]->toArray());

        $creditNotes = CreditNote::queryWithTenant(self::$company)
            ->where('invoice_id', self::$invoice)
            ->all();
        $this->assertCount(0, $creditNotes);
    }

    public function testApplyWithShortPay(): void
    {
        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => self::$invoice->total - 10,
                'short_pay' => 1,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);

        $transactions = Transaction::queryWithTenant(self::$company)
            ->where('payment_id', self::$payment)
            ->all();

        $this->assertCount(1, $transactions);
        $expected = [
            'id' => $transactions[0]->id(),
            'object' => 'transaction',
            'invoice' => self::$invoice->id(),
            'credit_note' => null,
            'customer' => self::$customer->id(),
            'date' => self::$payment->date,
            'type' => Transaction::TYPE_PAYMENT,
            'method' => PaymentMethod::OTHER,
            'gateway' => null,
            'gateway_id' => null,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'currency' => 'usd',
            'amount' => 90,
            'notes' => null,
            'parent_transaction' => null,
            'pdf_url' => self::$payment->pdf_url,
            'metadata' => new stdClass(),
            'created_at' => $transactions[0]->created_at,
            'updated_at' => $transactions[0]->updated_at,
            'estimate' => null,
            'payment_id' => self::$payment->id(),
        ];
        $this->assertEquals($expected, $transactions[0]->toArray());

        $creditNotes = CreditNote::queryWithTenant(self::$company)
            ->where('invoice_id', self::$invoice)
            ->all();
        $this->assertCount(1, $creditNotes);
        $expected = [
            'id' => $creditNotes[0]->id,
            'object' => 'credit_note',
            'customer' => self::$customer->id(),
            'name' => 'Credit Note',
            'currency' => 'usd',
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'description' => null,
                    'unit_cost' => 10.0,
                    'name' => 'Short Pay',
                    'type' => 'short_pay',
                    'amount' => 10.0,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'subtotal' => 10.0,
            'discounts' => [],
            'shipping' => [],
            'taxes' => [],
            'total' => 10.0,
            'balance' => 0.0,
            'paid' => true,
            'notes' => null,
            'number' => 'CN-00001',
            'purchase_order' => null,
            'date' => self::$payment->date,
            'draft' => false,
            'closed' => true,
            'url' => 'http://invoiced.localhost:1234/credit_notes/'.self::$company->identifier.'/'.$creditNotes[0]->client_id,
            'pdf_url' => 'http://invoiced.localhost:1234/credit_notes/'.self::$company->identifier.'/'.$creditNotes[0]->client_id.'/pdf',
            'status' => CreditNoteStatus::PAID,
            'metadata' => new stdClass(),
            'created_at' => $creditNotes[0]->created_at,
            'updated_at' => $creditNotes[0]->updated_at,
            'invoice' => self::$invoice->id(),
            'network_document_id' => null,
        ];

        $arr = $creditNotes[0]->toArray();

        // remove item ids
        foreach ($arr['items'] as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }

        $this->assertEquals($expected, $arr);
    }

    public function testVoidedPayment(): void
    {
        $this->expectException(ApplyPaymentException::class);
        $this->expectExceptionMessage('Cannot apply payment that is voided.');
        self::$payment->void();

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => self::$invoice->total,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);
    }

    public function testAppliedPayment(): void
    {
        $this->expectException(ApplyPaymentException::class);
        $this->expectExceptionMessage('Cannot apply payment that is already applied.');
        self::$payment->balance = 0;
        self::$payment->saveOrFail();

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => self::$invoice->total,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);
    }

    public function testNoTypeProvided(): void
    {
        $this->expectException(ApplyPaymentException::class);
        $this->expectExceptionMessage('Must provide a type for each transaction.');

        $splits = [
            [
                'invoice' => self::$invoice->id(),
                'amount' => self::$invoice->total,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);
    }

    public function testInvalidType(): void
    {
        $this->expectException(ApplyPaymentException::class);
        $this->expectExceptionMessage('Unrecognized split type: test');

        $splits = [
            [
                'type' => 'test',
                'invoice' => self::$invoice->id(),
                'amount' => self::$invoice->total,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);
    }

    public function testNoAmountProvided(): void
    {
        $this->expectException(ApplyPaymentException::class);
        $this->expectExceptionMessage('Must provide an amount for each transaction.');

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);
    }

    public function testNegativeAmount(): void
    {
        $this->expectException(ApplyPaymentException::class);
        $this->expectExceptionMessage('Amount applied to document must be greater than 0. Provided: -100 USD');

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => -self::$invoice->total,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);
    }

    public function testAmountGreaterThanBalance(): void
    {
        $this->expectException(ApplyPaymentException::class);
        $this->expectExceptionMessage('Total amount applied (201 USD) cannot exceed the payment amount (200 USD)');

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => 201,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);
    }

    public function testInvoiceNotFound(): void
    {
        $this->expectException(ApplyPaymentException::class);
        $this->expectExceptionMessage('Could not find invoice: 999');

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => 999,
                'amount' => 200,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, self::$customer, $splits);
    }

    public function testCustomerMismatch(): void
    {
        $this->expectException(ApplyPaymentException::class);

        $wrongCustomer = new Customer();
        $wrongCustomer->name = 'Sherlock';
        $wrongCustomer->email = 'sherlock@example.com';
        $wrongCustomer->address1 = 'Test';
        $wrongCustomer->address2 = 'Address';
        $wrongCustomer->saveOrFail();

        $this->expectExceptionMessage('The invoice provided ('.self::$invoice->number.') does not belong to the customer that was selected to apply payments for: '.$wrongCustomer->number);

        $splits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice->id(),
                'amount' => self::$invoice->total,
            ],
        ];

        $applyPayment = new ApplyPayment();
        $applyPayment->apply(self::$payment, $wrongCustomer, $splits);
    }
}
