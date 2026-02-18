<?php

namespace App\Tests\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\NetSuite\Writers\NetSuitePaymentWriter;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\Charge;

class NetSuitePaymentWriterTest extends AbstractWriterTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testToArray(): void
    {
        self::hasCustomer();
        self::hasPayment();
        self::$payment->customer = self::$customer->id;

        self::hasNetSuiteCustomer();
        $invoice1 = self::hasNetSuiteInvoice();
        self::hasInvoice();
        $invoice2 = self::$invoice;
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $creditNote->saveOrFail();

        $payment = new Payment();
        $payment->customer = self::$customer->id;
        $payment->amount = 200;
        $payment->currency = 'usd';
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice1,
                'invoice_number' => $invoice1->number,
                'amount' => 20,
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice2,
                'invoice_number' => $invoice2->number,
                'amount' => 20,
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => $invoice2,
                'invoice_number' => $invoice2->number,
                'credit_note' => $creditNote,
                'credit_note_number' => $creditNote->number,
                'credit_note_netsuite_id' => null,
                'amount' => 10,
            ],
            [
                'type' => PaymentItemType::ConvenienceFee->value,
                'amount' => 20,
            ],
        ];

        $payment->saveOrFail();
        $charge = self::makeCharge(self::$customer, $payment);

        $mapping = new AccountingCreditNoteMapping();
        $mapping->credit_note = $creditNote;
        $mapping->integration_id = IntegrationType::NetSuite->value;
        $mapping->accounting_id = '4';
        $mapping->source = AbstractMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $expectedAppliedTo = [
            [
                'id' => $payment->applied_to[0]['id'],
                'invoice' => $invoice1->id,
                'type' => 'invoice',
                'invoice_number' => $invoice1->number,
                'invoice_netsuite_id' => 3,
                'amount' => 20,
            ],
            [
                'id' => $payment->applied_to[1]['id'],
                'invoice' => $invoice2->id,
                'invoice_number' => $invoice2->number,
                'type' => 'invoice',
                'invoice_netsuite_id' => null,
                'amount' => 20,
            ],
            [
                'id' => $payment->applied_to[2]['id'],
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => $invoice2->id,
                'invoice_number' => $invoice2->number,
                'credit_note' => $creditNote->id,
                'credit_note_number' => $creditNote->number,
                'amount' => 10,
                'invoice_netsuite_id' => null,
                'credit_note_netsuite_id' => 4,
            ],
            [
                'id' => $payment->applied_to[3]['id'],
                'type' => PaymentItemType::ConvenienceFee->value,
                'amount' => 20,
            ],
        ];

        $valueObject = new NetSuitePaymentWriter($payment);
        $response = $valueObject->toArray();
        $this->assertEquals([
            'amount' => 200,
            'date' => $payment->date,
            'payment_netsuite_id' => null,
            'custbody_invoiced_id' => $payment->id,
            'customer' => 1,
            'customer_name' => self::$customer->name,
            'customer_number' => self::$customer->number,
            'customer_invoiced_id' => self::$customer->id,
            'applied' => $expectedAppliedTo,
            'voided' => false,
            'date_voided' => null,
            'method' => 'other',
            'charge' => [
                'checknum' => 'ch_test',
                'gateway' => 'test',
            ],
            'currency' => 'usd',
            'reference' => null,
            'parent_customer' => [
                'id' => self::$customer->id,
                'accountnumber' => 'CUST-00002',
                'companyname' => 'Sherlock',
                'netsuite_id' => '1',
            ],
            'id' => $payment->id,
            'netsuite_id' => null,
        ], $response);

        self::hasNetSuiteCustomer();
        $payment->customer = self::$customer->id;
        $payment->saveOrFail();

        // Payment to be updated
        $mapping = new AccountingPaymentMapping();
        $mapping->payment = $payment;
        $mapping->integration_id = IntegrationType::NetSuite->value;
        $mapping->accounting_id = '2';
        $mapping->source = AbstractMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        self::hasCard();
        $charge->setPaymentSource(self::$card);
        $charge->saveOrFail();
        $payment->charge = $charge;
        $payment->saveOrFail();
        $valueObject = new NetSuitePaymentWriter($payment);
        $response = $valueObject->toArray();

        $this->assertEquals($response, [
            'amount' => 200,
            'date' => $payment->date,
            'payment_netsuite_id' => 2,
            'custbody_invoiced_id' => $payment->id,
            'customer' => 1,
            'customer_name' => self::$customer->name,
            'customer_number' => self::$customer->number,
            'customer_invoiced_id' => self::$customer->id,
            'applied' => [],
            'voided' => false,
            'date_voided' => null,
            'method' => 'other',
            'charge' => [
                'checknum' => 'ch_test',
                'gateway' => 'test',
                'payment_source' => "Mastercard *4242 (expires Jan '91)",
                'type' => 'card',
            ],
            'currency' => 'usd',
            'reference' => null,
            'parent_customer' => [
                'id' => self::$customer->id,
                'accountnumber' => 'CUST-00003',
                'companyname' => 'Sherlock',
                'netsuite_id' => '1',
            ],
            'id' => $payment->id,
            'netsuite_id' => '2',
        ]);

        $payment->void();
        $valueObject = new NetSuitePaymentWriter($payment);
        $response = $valueObject->toArray();
        $this->assertEquals($response, [
            'amount' => 200,
            'date' => $payment->date,
            'payment_netsuite_id' => 2,
            'custbody_invoiced_id' => $payment->id,
            'customer' => 1,
            'customer_name' => self::$customer->name,
            'customer_number' => self::$customer->number,
            'customer_invoiced_id' => self::$customer->id,
            'applied' => [],
            'voided' => true,
            'date_voided' => $payment->date_voided,
            'method' => 'other',
            'charge' => [
                'checknum' => 'ch_test',
                'gateway' => 'test',
                'payment_source' => "Mastercard *4242 (expires Jan '91)",
                'type' => 'card',
            ],
            'currency' => 'usd',
            'reference' => null,
            'parent_customer' => [
                'id' => self::$customer->id,
                'accountnumber' => 'CUST-00003',
                'companyname' => 'Sherlock',
                'netsuite_id' => '1',
            ],
            'id' => $payment->id,
            'netsuite_id' => '2',
        ]);
    }

    /**
     * test to cover NetSuitePaymentWriter::toArray() when the payment is applied to a credit note.
     */
    public function testToArrayWithCreditNote(): void
    {
        self::hasCustomer();

        $obj = new Payment();
        $obj->customer = self::$customer->id;
        $obj->amount = 200;
        $obj->currency = 'usd';
        $obj->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $creditNote->saveOrFail();

        $obj->applied_to = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => null,
                'invoice_number' => null,
                'credit_note' => $creditNote->id,
                'credit_note_number' => $creditNote->number,
                'credit_note_netsuite_id' => null,
                'amount' => 10,
            ],
        ];

        $valueObject = new NetSuitePaymentWriter($obj);
        $response = $valueObject->toArray();

        $this->assertEquals([
            'amount' => 200,
            'date' => $obj->date,
            'payment_netsuite_id' => null,
            'custbody_invoiced_id' => $obj->id,
            'customer' => null,
            'customer_name' => self::$customer->name,
            'customer_number' => self::$customer->number,
            'customer_invoiced_id' => self::$customer->id,
            'applied' => [
                [
                    'type' => PaymentItemType::CreditNote->value,
                    'document_type' => 'invoice',
                    'invoice' => null,
                    'invoice_number' => null,
                    'credit_note' => $creditNote->id,
                    'credit_note_number' => $creditNote->number,
                    'amount' => 10,
                    'credit_note_netsuite_id' => null,
                ],
            ],
            'voided' => false,
            'date_voided' => null,
            'method' => 'other',
            'charge' => null,
            'currency' => 'usd',
            'reference' => null,
            'parent_customer' => [
                'id' => self::$customer->id,
                'accountnumber' => 'CUST-00004',
                'companyname' => 'Sherlock',
                'netsuite_id' => null,
            ],
            'id' => $obj->id,
            'netsuite_id' => null,
        ], $response);
    }

    private static function makeCharge(?Customer $customer = null, ?Payment $payment = null): Charge
    {
        $charge = new Charge();
        $charge->currency = 'usd';
        $charge->amount = self::$invoice->balance;
        $charge->status = Charge::PENDING;
        $charge->gateway = TestGateway::ID;
        $charge->gateway_id = 'ch_test';
        if ($customer) {
            $charge->customer = $customer;
        }
        if ($payment) {
            $charge->payment = $payment;
        }
        $charge->saveOrFail();

        return $charge;
    }
}
