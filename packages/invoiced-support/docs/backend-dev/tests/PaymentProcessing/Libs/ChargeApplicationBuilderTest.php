<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Libs\ChargeApplicationBuilder;
use App\PaymentProcessing\ValueObjects\AppliedCreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditNoteChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormItem;
use App\Tests\AppTestCase;

class ChargeApplicationBuilderTest extends AppTestCase
{
    public function testAddPaymentFormWithCreditNote(): void
    {
        $invoice1 = new Invoice();
        $invoice1->currency = 'usd';
        $invoice1->balance = 50;

        $invoice2 = new Invoice();
        $invoice2->currency = 'usd';
        $invoice2->balance = 100;

        // credit note less than 1 invoice
        $creditNote = new CreditNote();
        $creditNote->currency = 'usd';
        $creditNote->balance = 25;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 5000), document: $invoice1),
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', -2500), document: $creditNote),
                    new PaymentFormItem(new Money('usd', 37500)),
                ],
            )
        );

        $application = $builder->build();
        $splits = $application->getItems();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $this->assertCount(4, $splits);
        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(2500, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 25,
            'invoice' => $invoice1,
        ], $splits[0]->build());

        $this->assertInstanceOf(InvoiceChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(2500, $splits[1]->getAmount()->amount);
        $this->assertEquals([
            'type' => 'invoice',
            'amount' => 25,
            'invoice' => $invoice1,
        ], $splits[1]->build());

        $this->assertInstanceOf(InvoiceChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(10000, $splits[2]->getAmount()->amount);
        $this->assertEquals([
            'type' => 'invoice',
            'amount' => 100,
            'invoice' => $invoice2,
        ], $splits[2]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[3]);
        $this->assertEquals(37500, $splits[3]->getAmount()->amount);
        $this->assertEquals(37500, $splits[3]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 375,
        ], $splits[3]->build());

        // switch invoice places and apply fully credit note for one invoice
        $creditNote->balance = 100;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', 5000), document: $invoice1),
                    new PaymentFormItem(new Money('usd', -10000), document: $creditNote),
                    new PaymentFormItem(new Money('usd', 45000)),
                ],
            )
        );
        $application = $builder->build();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $splits = $application->getItems();
        $this->assertCount(3, $splits);
        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(10000, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 100,
            'invoice' => $invoice2,
        ], $splits[0]->build());

        $this->assertInstanceOf(InvoiceChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(5000, $splits[1]->getAmount()->amount);
        $this->assertEquals([
            'type' => 'invoice',
            'amount' => 50,
            'invoice' => $invoice1,
        ], $splits[1]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(45000, $splits[2]->getAmount()->amount);
        $this->assertEquals(45000, $splits[2]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 450,
        ], $splits[2]->build());

        // credit note 1 and half invoice
        $creditNote->balance = 125;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', 5000), document: $invoice1),
                    new PaymentFormItem(new Money('usd', -12500), document: $creditNote),
                    new PaymentFormItem(new Money('usd', 47500)),
                ],
            )
        );
        $application = $builder->build();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $splits = $application->getItems();
        $this->assertCount(4, $splits);
        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(10000, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 100,
            'invoice' => $invoice2,
        ], $splits[0]->build());

        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(2500, $splits[1]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 25,
            'invoice' => $invoice1,
        ], $splits[1]->build());

        $this->assertInstanceOf(InvoiceChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(2500, $splits[2]->getAmount()->amount);
        $this->assertEquals([
            'type' => 'invoice',
            'amount' => 25,
            'invoice' => $invoice1,
        ], $splits[2]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[3]);
        $this->assertEquals(47500, $splits[3]->getAmount()->amount);
        $this->assertEquals(47500, $splits[3]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 475,
        ], $splits[3]->build());

        // credit note 2 invoices
        $creditNote->balance = 150;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', 5000), document: $invoice1),
                    new PaymentFormItem(new Money('usd', -15000), document: $creditNote),
                    new PaymentFormItem(new Money('usd', 50000)),
                ],
            )
        );
        $application = $builder->build();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $splits = $application->getItems();
        $this->assertCount(3, $splits);
        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(10000, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 100,
            'invoice' => $invoice2,
        ], $splits[0]->build());

        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(5000, $splits[1]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 50,
            'invoice' => $invoice1,
        ], $splits[1]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(50000, $splits[2]->getAmount()->amount);
        $this->assertEquals(50000, $splits[2]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 500,
        ], $splits[2]->build());

        // credit more than 2 invoices
        $creditNote->balance = 200;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', 5000), document: $invoice1),
                    new PaymentFormItem(new Money('usd', -15000), document: $creditNote),
                    new PaymentFormItem(new Money('usd', 50000)),
                ],
            )
        );
        $application = $builder->build();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $splits = $application->getItems();
        $this->assertCount(3, $splits);
        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(10000, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 100,
            'invoice' => $invoice2,
        ], $splits[0]->build());

        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(5000, $splits[1]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 50,
            'invoice' => $invoice1,
        ], $splits[1]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(50000, $splits[2]->getAmount()->amount);
        $this->assertEquals(50000, $splits[2]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 500,
        ], $splits[2]->build());

        // credit more than 2 invoices
        $creditNote->balance = 250;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', 5000), document: $invoice1),
                    new PaymentFormItem(new Money('usd', -15000), document: $creditNote),
                    new PaymentFormItem(new Money('usd', 50000)),
                ],
            )
        );
        $application = $builder->build();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $splits = $application->getItems();
        $this->assertCount(3, $splits);
        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(10000, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 100,
            'invoice' => $invoice2,
        ], $splits[0]->build());

        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(5000, $splits[1]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 50,
            'invoice' => $invoice1,
        ], $splits[1]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(50000, $splits[2]->getAmount()->amount);
        $this->assertEquals(50000, $splits[2]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 500,
        ], $splits[2]->build());

        // two credit notes to one invoice
        $creditNote2 = new CreditNote();
        $creditNote2->currency = 'usd';
        $creditNote2->balance = 50;

        $creditNote->balance = 25;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', -2500), document: $creditNote),
                    new PaymentFormItem(new Money('usd', -5000), document: $creditNote2),
                    new PaymentFormItem(new Money('usd', 47500)),
                ],
            )
        );
        $application = $builder->build();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $splits = $application->getItems();
        $this->assertCount(4, $splits);
        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(2500, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 25,
            'invoice' => $invoice2,
        ], $splits[0]->build());

        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(5000, $splits[1]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote2,
            'amount' => 50,
            'invoice' => $invoice2,
        ], $splits[1]->build());

        $this->assertInstanceOf(InvoiceChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(2500, $splits[2]->getAmount()->amount);
        $this->assertEquals([
            'type' => 'invoice',
            'amount' => 25,
            'invoice' => $invoice2,
        ], $splits[2]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[3]);
        $this->assertEquals(47500, $splits[3]->getAmount()->amount);
        $this->assertEquals(47500, $splits[3]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 475,
        ], $splits[3]->build());

        // 2 credit notes (reverse order) to 2 invoice
        $creditNote->balance = 125;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', 5000), document: $invoice1),
                    new PaymentFormItem(new Money('usd', -5000), document: $creditNote2),
                    new PaymentFormItem(new Money('usd', -10000), document: $creditNote),
                    new PaymentFormItem(new Money('usd', 50000)),
                ],
            )
        );
        $application = $builder->build();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $splits = $application->getItems();
        $this->assertCount(4, $splits);
        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(5000, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote2,
            'amount' => 50,
            'invoice' => $invoice2,
        ], $splits[0]->build());

        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(5000, $splits[1]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 50,
            'invoice' => $invoice2,
        ], $splits[1]->build());

        $this->assertInstanceOf(CreditNoteChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(0, $splits[0]->getAmount()->amount);
        $this->assertEquals(5000, $splits[1]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit_note',
            'document_type' => 'invoice',
            'credit_note' => $creditNote,
            'amount' => 50,
            'invoice' => $invoice1,
        ], $splits[2]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[3]);
        $this->assertEquals(50000, $splits[3]->getAmount()->amount);
        $this->assertEquals(50000, $splits[3]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 500,
        ], $splits[3]->build());
    }

    public function testAddPaymentFormWithCreditBalance(): void
    {
        $invoice1 = new Invoice();
        $invoice1->currency = 'usd';
        $invoice1->balance = 50;

        $invoice2 = new Invoice();
        $invoice2->currency = 'usd';
        $invoice2->balance = 100;

        $builder = new ChargeApplicationBuilder();
        $builder->addPaymentForm(
            new PaymentForm(
                company: new Company(),
                customer: new Customer(),
                totalAmount: new Money('usd', 50000),
                paymentItems: [
                    new PaymentFormItem(new Money('usd', 5000), document: $invoice1),
                    new PaymentFormItem(new Money('usd', 10000), document: $invoice2),
                    new PaymentFormItem(new Money('usd', -2500)),
                    new PaymentFormItem(new Money('usd', 37500)),
                ],
            )
        );

        $application = $builder->build();
        $splits = $application->getItems();
        $this->assertEquals(50000, $application->getPaymentAmount()->amount);
        $this->assertCount(4, $splits);
        $this->assertInstanceOf(AppliedCreditChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(2500, $splits[0]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'applied_credit',
            'document_type' => 'invoice',
            'amount' => 25,
            'invoice' => $invoice1,
        ], $splits[0]->build());

        $this->assertInstanceOf(InvoiceChargeApplicationItem::class, $splits[1]);
        $this->assertEquals(2500, $splits[1]->getAmount()->amount);
        $this->assertEquals([
            'type' => 'invoice',
            'amount' => 25,
            'invoice' => $invoice1,
        ], $splits[1]->build());

        $this->assertInstanceOf(InvoiceChargeApplicationItem::class, $splits[2]);
        $this->assertEquals(10000, $splits[2]->getAmount()->amount);
        $this->assertEquals([
            'type' => 'invoice',
            'amount' => 100,
            'invoice' => $invoice2,
        ], $splits[2]->build());

        $this->assertInstanceOf(CreditChargeApplicationItem::class, $splits[3]);
        $this->assertEquals(37500, $splits[3]->getAmount()->amount);
        $this->assertEquals(37500, $splits[3]->getCredit()->amount);
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 375,
        ], $splits[3]->build());
    }
}
