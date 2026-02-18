<?php

namespace App\Tests\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\CurrencyConverter;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingConvenienceFeeMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Writers\IntacctArInvoiceWriter;
use App\Integrations\Intacct\Writers\IntacctCustomerWriter;
use App\Integrations\Intacct\Writers\IntacctOrderEntryInvoiceWriter;
use App\Integrations\Intacct\Writers\IntacctPaymentWriter;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Intacct\Functions\AccountsReceivable\ArPaymentApply;
use Intacct\Functions\AccountsReceivable\ArPaymentCreate;
use Intacct\Functions\AccountsReceivable\ArPaymentReverse;
use Intacct\Functions\AccountsReceivable\InvoiceCreate;
use Intacct\Functions\AccountsReceivable\InvoiceLineCreate;
use Mockery;
use Symfony\Component\EventDispatcher\EventDispatcher;

class IntacctPaymentWriterTest extends AppTestCase
{
    private static IntacctSyncProfile $syncProfile;
    private static Payment $originalPayment;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        self::hasIntacctAccount();
        self::$syncProfile = new IntacctSyncProfile();
        self::$syncProfile->invoice_start_date = (new CarbonImmutable('2015-03-19'))->getTimestamp();
        self::$syncProfile->payment_accounts = [
            ['currency' => '*', 'method' => '*', 'undeposited_funds' => true, 'account' => '100'],
            ['currency' => 'cad', 'method' => '*', 'undeposited_funds' => false, 'account' => 'WF01'],
            ['currency' => '*', 'method' => 'direct_debit', 'undeposited_funds' => false, 'account' => 'DD01'],
        ];
        self::$syncProfile->customer_top_level = false;
        self::$syncProfile->saveOrFail();

        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = self::$invoice;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '534';
        $mapping->source = AccountingInvoiceMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();
    }

    private function getWriter(IntacctApi $intacctApi, ?CurrencyConverter $currencyConverter = null, ?IntacctArInvoiceWriter $arInvoiceWriter = null, ?IntacctOrderEntryInvoiceWriter $oeInvoiceWriter = null): IntacctPaymentWriter
    {
        $currencyConverter = $currencyConverter ?? Mockery::mock(CurrencyConverter::class);

        $customerWriter = Mockery::mock(IntacctCustomerWriter::class);
        $customerWriter->shouldReceive('createIntacctCustomer')
            ->andReturn('0'); // return isn't used so it doesnt matter
        $customerWriter->shouldReceive('getIntacctEntity')->andReturn(null);

        /** @var Mockery\Mock $arInvoiceWriter */
        $arInvoiceWriter = $arInvoiceWriter ?? Mockery::mock(IntacctArInvoiceWriter::class);
        $arInvoiceWriter->shouldReceive('setSyncProfile');
        /** @var Mockery\Mock $oeInvoiceWriter */
        $oeInvoiceWriter = $oeInvoiceWriter ?? Mockery::mock(IntacctOrderEntryInvoiceWriter::class);
        $oeInvoiceWriter->shouldReceive('setSyncProfile');

        $writer = new IntacctPaymentWriter($intacctApi, $currencyConverter, new EventDispatcher(), $customerWriter, $arInvoiceWriter, $oeInvoiceWriter);
        $writer->setStatsd(new StatsdClient());
        $writer->setLogger(self::$logger);

        return $writer;
    }

    public function testIsEnabled(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $writer = $this->getWriter($intacctApi);
        $this->assertFalse($writer->isEnabled(new IntacctSyncProfile(['write_payments' => false])));
        $this->assertTrue($writer->isEnabled(new IntacctSyncProfile(['write_payments' => true])));
    }

    public function testCreate(): void
    {
        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 1;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();
        self::$originalPayment = $payment;

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentCreate $intacctPayment) use ($payment) {
                $this->assertEquals(1.0, $intacctPayment->getTransactionPaymentAmount());
                $this->assertEquals(new CarbonImmutable('2019-11-10'), $intacctPayment->getReceivedDate());
                $this->assertEquals('CUST-00001', $intacctPayment->getCustomerId());
                $this->assertEquals('EFT', $intacctPayment->getPaymentMethod());
                $this->assertEquals('Invoiced ID: '.$payment->id(), $intacctPayment->getReferenceNumber());
                $this->assertEquals('100', $intacctPayment->getUndepositedFundsGlAccountNo());

                $splits = $intacctPayment->getApplyToTransactions();
                $this->assertCount(1, $splits);
                $this->assertEquals(1, $splits[0]->getAmountToApply());
                $this->assertEquals('534', $splits[0]->getApplyToRecordId());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testCreateCharge(): void
    {
        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 1;
        $payment->method = PaymentMethod::CREDIT_CARD;
        $payment->reference = '67483';
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();

        $charge = new Charge();
        $charge->payment = $payment;
        $charge->currency = $payment->currency;
        $charge->amount = $payment->amount;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = 'invoiced';
        $charge->gateway_id = '67483';
        $charge->saveOrFail();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentCreate $intacctPayment) use ($payment) {
                $this->assertEquals(1.0, $intacctPayment->getTransactionPaymentAmount());
                $this->assertEquals(new CarbonImmutable('2019-11-10'), $intacctPayment->getReceivedDate());
                $this->assertEquals('CUST-00001', $intacctPayment->getCustomerId());
                $this->assertEquals('Credit Card', $intacctPayment->getPaymentMethod());
                $this->assertEquals('Reference: 67483, Gateway: invoiced, Invoiced ID: '.$payment->id(), $intacctPayment->getReferenceNumber());
                $this->assertEquals('100', $intacctPayment->getUndepositedFundsGlAccountNo());

                $splits = $intacctPayment->getApplyToTransactions();
                $this->assertCount(1, $splits);
                $this->assertEquals(1, $splits[0]->getAmountToApply());
                $this->assertEquals('534', $splits[0]->getApplyToRecordId());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testCreateIntacctException(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('getAccount');
        $intacctApi->shouldReceive('createObject')
            ->andThrow(new IntegrationApiException('test'));

        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 1;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();

        $writer = $this->getWriter($intacctApi);

        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        // should create a reconciliation error
        $error = ReconciliationError::where('object', 'payment')
            ->where('object_id', $payment)
            ->oneOrNull();

        $this->assertInstanceOf(ReconciliationError::class, $error);
        $this->assertEquals('test', $error->message);
        $this->assertEquals(IntegrationType::Intacct->value, $error->integration_id);
        $this->assertEquals(ReconciliationError::LEVEL_ERROR, $error->level);
    }

    public function testPaymentWithNoInvoice(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldNotReceive('createObject');

        /**
         * $payment->invoice is purposely not set in order
         * to test payments that are not attached to an invoice.
         */
        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 1;
        $payment->saveOrFail();

        $writer = $this->getWriter($intacctApi);
        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $error = ReconciliationError::where('object', 'payment')
            ->where('object_id', $payment)
            ->oneOrNull();
        $this->assertNull($error);
    }

    public function testCreateMultipleInvoices(): void
    {
        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [['unit_cost' => 5]];
        $invoice2->saveOrFail();

        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = $invoice2;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '934';
        $mapping->source = AccountingInvoiceMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 6;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice,
                'amount' => 1,
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice2,
                'amount' => 5,
            ],
        ];
        $payment->saveOrFail();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentCreate $intacctPayment) use ($payment) {
                $this->assertEquals(6, $intacctPayment->getTransactionPaymentAmount());
                $this->assertEquals(new CarbonImmutable('2019-11-10'), $intacctPayment->getReceivedDate());
                $this->assertEquals('CUST-00001', $intacctPayment->getCustomerId());
                $this->assertEquals('EFT', $intacctPayment->getPaymentMethod());
                $this->assertEquals('Invoiced ID: '.$payment->id(), $intacctPayment->getReferenceNumber());
                $this->assertEquals('100', $intacctPayment->getUndepositedFundsGlAccountNo());

                $splits = $intacctPayment->getApplyToTransactions();
                $this->assertCount(2, $splits);
                $this->assertEquals(1, $splits[0]->getAmountToApply());
                $this->assertEquals('534', $splits[0]->getApplyToRecordId());
                $this->assertEquals(5, $splits[1]->getAmountToApply());
                $this->assertEquals('934', $splits[1]->getApplyToRecordId());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testCreatePaymentMethodMapping(): void
    {
        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 1;
        $payment->method = PaymentMethod::DIRECT_DEBIT;
        $payment->reference = '67483';
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();
        self::$originalPayment = $payment;

        $charge = new Charge();
        $charge->payment = $payment;
        $charge->currency = $payment->currency;
        $charge->amount = $payment->amount;
        $charge->status = Charge::SUCCEEDED;
        $charge->gateway = 'gocardless';
        $charge->gateway_id = '67483';
        $charge->saveOrFail();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentCreate $intacctPayment) use ($payment) {
                $this->assertEquals(1.0, $intacctPayment->getTransactionPaymentAmount());
                $this->assertEquals(new CarbonImmutable('2019-11-10'), $intacctPayment->getReceivedDate());
                $this->assertEquals('CUST-00001', $intacctPayment->getCustomerId());
                $this->assertEquals('EFT', $intacctPayment->getPaymentMethod());
                $this->assertEquals('Reference: 67483, Gateway: gocardless, Invoiced ID: '.$payment->id(), $intacctPayment->getReferenceNumber());
                $this->assertEquals('DD01', $intacctPayment->getBankAccountId());

                $splits = $intacctPayment->getApplyToTransactions();
                $this->assertCount(1, $splits);
                $this->assertEquals(1, $splits[0]->getAmountToApply());
                $this->assertEquals('534', $splits[0]->getApplyToRecordId());

                return '1234';
            })
            ->once();

        $writer = $this->getWriter($intacctApi);

        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    /**
     * @depends testCreate
     */
    public function testVoid(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentReverse $intacctRefund) {
                $this->assertEquals('1234', $intacctRefund->getRecordNo());
                $this->assertEquals(new CarbonImmutable('2019-11-10'), $intacctRefund->getReverseDate());

                return '4567';
            })
            ->once();

        self::$originalPayment->void();
        self::$originalPayment->date_voided = (int) mktime(0, 0, 0, 11, 10, 2019);

        $writer = $this->getWriter($intacctApi);

        $writer->update(self::$originalPayment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail(self::$originalPayment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testCreateMultiCurrency(): void
    {
        self::$company->features->enable('multi_currency');

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'cad';
        $invoice->items = [['unit_cost' => 1]];
        $invoice->saveOrFail();

        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = $invoice;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '2340';
        $mapping->source = AccountingInvoiceMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $payment = new Payment();
        $payment->currency = 'cad';
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 1;
        $payment->method = PaymentMethod::CREDIT_CARD;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentCreate $intacctPayment) use ($payment) {
                $this->assertEquals(1.0, $intacctPayment->getTransactionPaymentAmount());
                $this->assertEquals(new CarbonImmutable('2019-11-10'), $intacctPayment->getReceivedDate());
                $this->assertEquals('CUST-00001', $intacctPayment->getCustomerId());
                $this->assertEquals('EFT', $intacctPayment->getPaymentMethod());
                $this->assertEquals('Invoiced ID: '.$payment->id(), $intacctPayment->getReferenceNumber());
                $this->assertEquals('WF01', $intacctPayment->getBankAccountId());
                $this->assertEquals('USD', $intacctPayment->getBaseCurrency());
                $this->assertEquals('CAD', $intacctPayment->getTransactionCurrency());
                $this->assertEquals('Intacct Daily Rate', $intacctPayment->getExchangeRateType());
                $this->assertEquals(0.77, $intacctPayment->getBasePaymentAmount());

                $splits = $intacctPayment->getApplyToTransactions();
                $this->assertCount(1, $splits);
                $this->assertEquals(1, $splits[0]->getAmountToApply());
                $this->assertEquals('2340', $splits[0]->getApplyToRecordId());

                return '4321';
            })
            ->once();

        $currencyConverter = Mockery::mock(CurrencyConverter::class);
        $currencyConverter->shouldReceive('convert')
            ->andReturnUsing(function (Money $amount, string $currency) {
                $this->assertEquals('cad', $amount->currency);
                $this->assertEquals(100, $amount->amount);
                $this->assertEquals('USD', $currency);

                return new Money('usd', 77);
            });

        $writer = $this->getWriter($intacctApi, $currencyConverter);

        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('4321', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);

        self::$company->features->disable('multi_currency');
    }

    public function testCreateCreditNoteApplication(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentApply $intacctApply) {
                // Record number should be that of the mapped credit note
                // since ArPaymentApply requests don't return a record number.
                // See functionality in IntacctPaymentWriter.
                $this->assertEquals('1111', $intacctApply->getRecordNo());

                $transactions = $intacctApply->getApplyToTransactions();

                // Only one transaction should occur when a credit note is applied.
                $this->assertEquals(1, count($transactions));
                $this->assertEquals(1.0, $transactions[0]->getAmountToApply());
                $this->assertEquals('534', $transactions[0]->getApplyToRecordId());

                return '1112';
            })
            ->once();

        // Create test credit note.
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

        // Create mapping for credit note
        $mapping = new AccountingCreditNoteMapping();
        $mapping->credit_note = $creditNote;
        $mapping->accounting_id = '1111';
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->source = AccountingCreditNoteMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        // Create test payment.
        $payment = new Payment();
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->currency = 'usd';
        $payment->amount = 1;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'credit_note' => $creditNote,
                'document_type' => 'invoice',
                'invoice' => self::$invoice,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();

        // Test writer.
        $writer = $this->getWriter($intacctApi);
        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1112', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testConvenienceFee(): void
    {
        self::$syncProfile->write_convenience_fees = true;
        self::$syncProfile->convenience_fee_account = 'cf_account';
        self::$syncProfile->saveOrFail();

        $payment = new Payment();
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 2;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice,
                'amount' => 1,
            ],
            [
                'type' => PaymentItemType::ConvenienceFee->value,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();

        $arInvoiceWriter = Mockery::mock(IntacctArInvoiceWriter::class);
        $arInvoiceWriter->shouldReceive('buildCreateRequest')
            ->andReturnUsing(function () {
                $invoice = new InvoiceCreate();
                $invoice->setLines([new InvoiceLineCreate()]);

                return $invoice;
            });

        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (InvoiceCreate $intacctInvoice) {
                $this->assertEquals('cf_account', $intacctInvoice->getLines()[0]->getGlAccountNumber());

                return '1235';
            })
            ->once();
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentCreate $intacctPayment) {
                $this->assertCount(2, $intacctPayment->getApplyToTransactions());

                return '1236';
            })
            ->once();
        $writer = $this->getWriter($intacctApi, null, $arInvoiceWriter);
        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1236', $mapping->accounting_id);

        $feeMapping = AccountingConvenienceFeeMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1235', $feeMapping->accounting_id);
    }

    public function testCreateInvoiceApplicationNotMapped(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');

        // Create test invoice.
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->metadata = (object) ['intacct_document_type' => 'Sales Invoice'];
        $invoice->saveOrFail();
        $intacctApi->shouldReceive('getOrderEntryTransactionPrRecordKey')
            ->withArgs(['Sales Invoice', $invoice->number])
            ->andReturn('5');

        // Create test payment.
        $payment = new Payment();
        $payment->currency = 'usd';
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->amount = 1;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentCreate $intacctPayment) use ($payment) {
                $this->assertEquals(1.0, $intacctPayment->getTransactionPaymentAmount());
                $this->assertEquals(new CarbonImmutable('2019-11-10'), $intacctPayment->getReceivedDate());
                $this->assertEquals('CUST-00001', $intacctPayment->getCustomerId());
                $this->assertEquals('EFT', $intacctPayment->getPaymentMethod());
                $this->assertEquals('Invoiced ID: '.$payment->id(), $intacctPayment->getReferenceNumber());
                $this->assertEquals('100', $intacctPayment->getUndepositedFundsGlAccountNo());

                $splits = $intacctPayment->getApplyToTransactions();
                $this->assertCount(1, $splits);
                $this->assertEquals(1, $splits[0]->getAmountToApply());
                $this->assertEquals('5', $splits[0]->getApplyToRecordId());

                return '1234';
            })
            ->once();

        // Test the writer.
        $writer = $this->getWriter($intacctApi);
        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        // Validate the result.
        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }

    public function testCreateCreditNoteApplicationNotMapped(): void
    {
        $intacctApi = Mockery::mock(IntacctApi::class);
        $intacctApi->shouldReceive('setAccount');
        $intacctApi->shouldReceive('createObject')
            ->andReturnUsing(function (ArPaymentApply $intacctApply) {
                // Record number should be that of the mapped credit note
                // since ArPaymentApply requests don't return a record number.
                // See functionality in IntacctPaymentWriter.
                $this->assertEquals('4', $intacctApply->getRecordNo());

                $transactions = $intacctApply->getApplyToTransactions();

                // Only one transaction should occur when a credit note is applied.
                $this->assertEquals(1, count($transactions));
                $this->assertEquals(1.0, $transactions[0]->getAmountToApply());
                $this->assertEquals('3', $transactions[0]->getApplyToRecordId());

                return '5';
            })
            ->once();

        // Create test invoice.
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->metadata = (object) ['intacct_document_type' => 'Sales Invoice'];
        $invoice->saveOrFail();
        $intacctApi->shouldReceive('getOrderEntryTransactionPrRecordKey')
            ->withArgs(['Sales Invoice', $invoice->number])
            ->andReturn('3');

        // Create test credit note.
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->metadata = (object) ['intacct_document_type' => 'Sales Return'];
        $creditNote->saveOrFail();
        $intacctApi->shouldReceive('getOrderEntryTransactionPrRecordKey')
            ->withArgs(['Sales Return', $creditNote->number])
            ->andReturn('4');

        // Create test payment.
        $payment = new Payment();
        $payment->date = (int) mktime(0, 0, 0, 11, 10, 2019);
        $payment->setCustomer(self::$customer);
        $payment->currency = 'usd';
        $payment->amount = 0;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'credit_note' => $creditNote,
                'document_type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 1,
            ],
        ];
        $payment->saveOrFail();

        // Test writer.
        $writer = $this->getWriter($intacctApi);
        $writer->create($payment, self::$intacctAccount, self::$syncProfile);

        $mapping = AccountingPaymentMapping::findOrFail($payment->id());
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->integration_id);
        $this->assertEquals('5', $mapping->accounting_id);
        $this->assertGreaterThan(0, self::$syncProfile->refresh()->last_synced);
    }
}
