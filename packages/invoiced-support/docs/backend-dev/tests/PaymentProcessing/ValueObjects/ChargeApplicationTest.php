<?php

namespace App\Tests\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\AppliedCreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ConvenienceFeeChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\CreditNoteChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\EstimateChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use App\Tests\AppTestCase;

class ChargeApplicationTest extends AppTestCase
{
    public function testBuildEstimate(): void
    {
        $estimate = new Estimate(['id' => 123]);
        $split = new EstimateChargeApplicationItem(new Money('usd', 50), $estimate);
        $this->assertEquals([
            'type' => 'estimate',
            'amount' => 0.5,
            'estimate' => $estimate,
        ], $split->build());
    }

    public function testBuildOverpayment(): void
    {
        $split = new CreditChargeApplicationItem(new Money('usd', 50));
        $this->assertEquals([
            'type' => 'credit',
            'amount' => 0.5,
        ], $split->build());
    }

    public function testBuildInvoice(): void
    {
        $invoice = new Invoice(['id' => 123]);
        $split = new InvoiceChargeApplicationItem(new Money('usd', 50), $invoice);
        $this->assertEquals([
            'type' => 'invoice',
            'amount' => 0.5,
            'invoice' => $invoice,
        ], $split->build());
    }

    public function testBuildCreditNote(): void
    {
        $invoice = new Invoice(['id' => 123]);
        $creditNote = new CreditNote(['id' => 123]);
        $split = new CreditNoteChargeApplicationItem(new Money('usd', 50), $creditNote, $invoice);
        $this->assertEquals([
            'type' => 'credit_note',
            'credit_note' => $creditNote,
            'amount' => 0.5,
            'invoice' => $invoice,
            'document_type' => 'invoice',
        ], $split->build());
    }

    public function testBuildAppliedCredit(): void
    {
        $invoice = new Invoice(['id' => 123]);
        $split = new AppliedCreditChargeApplicationItem(new Money('usd', 50), $invoice);
        $this->assertEquals([
            'type' => 'applied_credit',
            'amount' => 0.5,
            'document_type' => 'invoice',
            'invoice' => $invoice,
        ], $split->build());
    }

    public function testBuildConvenienceFee(): void
    {
        $split = new ConvenienceFeeChargeApplicationItem(new Money('usd', 50));
        $this->assertEquals([
            'type' => 'convenience_fee',
            'amount' => 0.5,
        ], $split->build());
    }

    public function testAddEstimate(): void
    {
        $estimate = new Estimate(['id' => 123]);
        $split = new EstimateChargeApplicationItem(new Money('usd', 50), $estimate);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);

        $splits = $chargeApplication->getItems();
        $this->assertCount(1, $splits);
        $this->assertInstanceOf(EstimateChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(50, $splits[0]->getAmount()->amount);
    }

    public function testAddInvoice(): void
    {
        $invoice = new Invoice(['id' => 123]);
        $split = new InvoiceChargeApplicationItem(new Money('usd', 50), $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);

        $this->assertEquals(new Money('usd', 50), $chargeApplication->getPaymentAmount());
        $splits = $chargeApplication->getItems();
        $this->assertCount(1, $splits);
        $this->assertInstanceOf(InvoiceChargeApplicationItem::class, $splits[0]);
        $this->assertEquals(50, $splits[0]->getAmount()->amount);

        $invoice1 = new Invoice();
        $invoice1->currency = 'usd';
        $invoice1->balance = 50;

        $invoice2 = new Invoice();
        $invoice2->currency = 'usd';
        $invoice2->balance = 100;

        $items = [
            new InvoiceChargeApplicationItem(new Money('usd', 5000), $invoice1),
            new InvoiceChargeApplicationItem(new Money('usd', 5000), $invoice2),
        ];
        $chargeApplication = new ChargeApplication($items, PaymentFlowSource::Charge);

        $this->assertEquals(new Money('usd', 10000), $chargeApplication->getPaymentAmount());
    }

    public function testAddAppliedCredit(): void
    {
        $invoice = new Invoice(['id' => 123]);
        $split = new AppliedCreditChargeApplicationItem(new Money('usd', 50), $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);

        $this->assertEquals(new Money('usd', 0), $chargeApplication->getPaymentAmount());
    }

    public function testAddOverpayment(): void
    {
        $invoice1 = new Invoice();
        $invoice1->currency = 'usd';
        $invoice1->balance = 50;

        $invoice2 = new Invoice();
        $invoice2->currency = 'usd';
        $invoice2->balance = 100;

        $items = [
            new InvoiceChargeApplicationItem(new Money('usd', 5000), $invoice1),
            new InvoiceChargeApplicationItem(new Money('usd', 10000), $invoice2),
            new CreditChargeApplicationItem(new Money('usd', 35000)),
        ];
        $chargeApplication = new ChargeApplication($items, PaymentFlowSource::Charge);

        $this->assertEquals(new Money('usd', 50000), $chargeApplication->getPaymentAmount());
    }

    public function testValidateAmountEmpty(): void
    {
        $this->expectException(ChargeException::class);
        $this->expectExceptionMessage('Payment cannot be empty');

        $chargeApplication = new ChargeApplication([], PaymentFlowSource::Charge);
        $chargeApplication->validateAmount('stripe', PaymentMethod::CREDIT_CARD);
    }

    public function testValidateAmountBelowMinimum(): void
    {
        $this->expectException(ChargeException::class);
        $this->expectExceptionMessage('Payment amount cannot be less than 0.01 XXX');

        $invoice = new Invoice();
        $invoice->currency = 'xxx';
        $invoice->balance = 100;

        $amount = Money::fromDecimal('xxx', 0);
        $split = new InvoiceChargeApplicationItem($amount, $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);

        $chargeApplication->validateAmount('test', PaymentMethod::CREDIT_CARD);
    }

    public function testValidateAmountUnsupportedCurrency(): void
    {
        $this->expectException(ChargeException::class);
        $this->expectExceptionMessage('The stripe payment gateway / credit_card payment method does not support the \'xxx\' currency.');

        $invoice = new Invoice();
        $invoice->currency = 'xxx';
        $invoice->balance = 100;

        $amount = Money::fromDecimal('xxx', 50);
        $split = new InvoiceChargeApplicationItem($amount, $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);

        $chargeApplication->validateAmount('stripe', PaymentMethod::CREDIT_CARD);
    }

    public function testValidateAmountZeroLineItem(): void
    {
        $this->expectException(ChargeException::class);
        $this->expectExceptionMessage('Payment line item amount must be greater than zero. Document: INV-90001');

        $invoice1 = new Invoice();
        $invoice1->currency = 'usd';
        $invoice1->balance = 100;

        $invoice2 = new Invoice();
        $invoice2->number = 'INV-90001';
        $invoice2->currency = 'usd';
        $invoice2->balance = 100;

        $split = new InvoiceChargeApplicationItem(new Money('usd', 10000), $invoice1);
        $split2 = new InvoiceChargeApplicationItem(Money::zero('usd'), $invoice2);
        $chargeApplication = new ChargeApplication([$split, $split2], PaymentFlowSource::Charge);

        $chargeApplication->validateAmount('stripe', PaymentMethod::CREDIT_CARD);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateAmountPendingCreditNote(): void
    {
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->balance = 200;

        $creditNote = new CreditNote();
        $creditNote->currency = 'usd';
        $creditNote->balance = 100;

        $split = new CreditNoteChargeApplicationItem(new Money('usd', 10000), $creditNote, $invoice);
        $split2 = new InvoiceChargeApplicationItem(new Money('usd', 10000), $invoice);
        $chargeApplication = new ChargeApplication([$split, $split2], PaymentFlowSource::Charge);

        $chargeApplication->validateAmount('stripe', PaymentMethod::CREDIT_CARD);
    }

    public function testValidateDocumentsPendingInvoice(): void
    {
        $this->expectException(ChargeException::class);
        $this->expectExceptionMessage("Payment cannot be processed because it's applied to an invoice with a pending payment");

        // should throw exception on pending invoice
        $invoice = new Invoice();
        $invoice->status = InvoiceStatus::Pending->value;
        $invoice->currency = 'xxx';
        $invoice->balance = 100;

        $amount = Money::fromDecimal('xxx', 100);
        $split = new InvoiceChargeApplicationItem($amount, $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);

        $chargeApplication->validateDocuments(new Customer());
    }

    public function testValidateDocumentsMismatchedCustomer(): void
    {
        $this->expectException(ChargeException::class);
        $this->expectExceptionMessage('The invoice provided (INV-00001) does not belong to the customer that was selected to apply payments for: CUST-00001');

        // should throw exception on pending invoice
        $invoice = new Invoice();
        $invoice->number = 'INV-00001';
        $invoice->setCustomer(new Customer(['id' => -2]));
        $invoice->currency = 'xxx';
        $invoice->balance = 100;

        $amount = Money::fromDecimal('xxx', 100);
        $split = new InvoiceChargeApplicationItem($amount, $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);

        $chargeApplication->validateDocuments(new Customer(['id' => -1, 'number' => 'CUST-00001']));
    }

    public function testApplyConvenienceFee(): void
    {
        $customer = new Customer();
        $customer->convenience_fee = true;

        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->balance = 100;

        $amount = Money::fromDecimal('usd', 100);
        $split = new InvoiceChargeApplicationItem($amount, $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);

        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $method->convenience_fee = 0;

        // Try with method convenience fee disabled
        $chargeApplication->applyConvenienceFee($method, $customer);

        $this->assertCount(1, $chargeApplication->getItems());
        $this->assertEquals(10000, $chargeApplication->getPaymentAmount()->amount);

        // Try with customer convenience fee disabled
        $method->convenience_fee = 290;
        $customer->convenience_fee = false;
        $chargeApplication->applyConvenienceFee($method, $customer);

        $this->assertCount(1, $chargeApplication->getItems());
        $this->assertEquals(10000, $chargeApplication->getPaymentAmount()->amount);

        // Enable convenience fees
        $customer->convenience_fee = true;
        $chargeApplication->applyConvenienceFee($method, $customer);

        $this->assertCount(2, $chargeApplication->getItems());
        $this->assertEquals(10290, $chargeApplication->getPaymentAmount()->amount);
    }
}
