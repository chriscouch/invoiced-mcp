<?php

namespace App\Tests\Integrations\QuickBooksOnline\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Models\AccountingConvenienceFeeMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksCreditNoteWriter;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksCustomerWriter;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksInvoiceWriter;
use App\Integrations\QuickBooksOnline\Writers\QuickBooksPaymentWriter;
use App\Tests\AppTestCase;
use Mockery;

class QuickBooksPaymentWriterTest extends AppTestCase
{
    private static QuickBooksOnlineSyncProfile $syncProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasEstimate();
        self::$syncProfile = new QuickBooksOnlineSyncProfile();
        self::$syncProfile->tax_code = 'TAX';
    }

    //
    // Helpers
    //

    /**
     * Returns a \stdClass instance representing a QBO Customer object.
     * The object returned only consists of the Id property
     * because no other property is used by the writer.
     */
    public function getExpectedQBOObject(string $id): \stdClass
    {
        return (object) [
            'Id' => $id,
        ];
    }

    /**
     * Returns instance of QuickBooksPaymentWriter configured
     * for test cases.
     *
     * Both the QuickBooksApi and QuickBooksInvoiceWriter instances should be mocks.
     */
    public function getWriter(QuickBooksApi $api, QuickBooksInvoiceWriter $invoiceWriter, QuickBooksCreditNoteWriter $creditNoteWriter, QuickBooksCustomerWriter $customerWriter): QuickBooksPaymentWriter
    {
        $writer = new QuickBooksPaymentWriter($api, $invoiceWriter, $creditNoteWriter, $customerWriter);
        $writer->setStatsd(new StatsdClient());

        return $writer;
    }

    public function mapInvoice(Invoice $invoice, string $accountingId): AccountingInvoiceMapping
    {
        $mapping = new AccountingInvoiceMapping();
        $mapping->accounting_id = $accountingId;
        $mapping->invoice = $invoice;
        $mapping->source = AccountingInvoiceMapping::SOURCE_INVOICED;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->saveOrFail();

        return $mapping;
    }

    public function mapCreditNote(CreditNote $creditNote, string $accountingId): AccountingCreditNoteMapping
    {
        $mapping = new AccountingCreditNoteMapping();
        $mapping->accounting_id = $accountingId;
        $mapping->credit_note = $creditNote;
        $mapping->source = AccountingInvoiceMapping::SOURCE_INVOICED;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->saveOrFail();

        return $mapping;
    }

    public function mapCustomer(Customer $customer, string $accountingId): AccountingCustomerMapping
    {
        $mapping = new AccountingCustomerMapping();
        $mapping->accounting_id = $accountingId;
        $mapping->customer = $customer;
        $mapping->source = AccountingInvoiceMapping::SOURCE_INVOICED;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->saveOrFail();

        return $mapping;
    }

    public function enableCustomerWrites(): void
    {
        self::$syncProfile->write_customers = true;
        self::$syncProfile->saveOrFail();
    }

    public function disableCustomerWrites(): void
    {
        self::$syncProfile->write_customers = false;
        self::$syncProfile->saveOrFail();
    }

    public function enableInvoiceWrites(): void
    {
        self::$syncProfile->write_invoices = true;
        self::$syncProfile->saveOrFail();
    }

    public function disableInvoiceWrites(): void
    {
        self::$syncProfile->write_invoices = false;
        self::$syncProfile->saveOrFail();
    }

    public function enableConvenienceFeeWrites(): void
    {
        self::$syncProfile->write_convenience_fees = true;
        self::$syncProfile->saveOrFail();
    }

    public function disableConvenienceFeeWrites(): void
    {
        self::$syncProfile->write_convenience_fees = false;
        self::$syncProfile->saveOrFail();
    }

    //
    // Tests
    //

    public function testIsEnabled(): void
    {
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $customerWriter = new QuickBooksCustomerWriter($quickbooksApi);
        $creditNoteWriter = new QuickBooksCreditNoteWriter($quickbooksApi, $customerWriter);
        $invoiceWriter = new QuickBooksInvoiceWriter($quickbooksApi, $customerWriter);
        $writer = $this->getWriter($quickbooksApi, $invoiceWriter, $creditNoteWriter, $customerWriter);
        $this->assertFalse($writer->isEnabled(new QuickBooksOnlineSyncProfile(['write_payments' => false])));
        $this->assertTrue($writer->isEnabled(new QuickBooksOnlineSyncProfile(['write_payments' => true])));
    }

    public function testBuildQBOPaymentDetails(): void
    {
        // test (fake) qbo ids
        $qboCustomerId = '1';
        $qboInvoice1Id = '1';
        $qboInvoice2Id = '2';
        $qboCreditMemoId = '8';
        $qboPaymentMethodId = '9';
        $undepositedFundsAccountId = '1';

        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive([
            'getPaymentMethodByName' => $this->getExpectedQBOObject($qboPaymentMethodId),
            'getAccountByName' => $this->getExpectedQBOObject($undepositedFundsAccountId),
            'setAccount' => null,
        ]);
        $customerWriter = Mockery::mock(QuickBooksCustomerWriter::class, [$quickbooksApi])->makePartial();
        $creditNoteWriter = Mockery::mock(QuickBooksCreditNoteWriter::class, [$quickbooksApi, $customerWriter])->makePartial();
        $invoiceWriter = Mockery::mock(QuickBooksInvoiceWriter::class, [$quickbooksApi, $customerWriter])->shouldAllowMockingProtectedMethods()->makePartial();
        $writer = $this->getWriter($quickbooksApi, $invoiceWriter, $creditNoteWriter, $customerWriter);

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

        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice2->saveOrFail();

        // create fake mappings
        $invoice1Mapping = $this->mapInvoice(self::$invoice, $qboInvoice1Id);
        $invoice2Mapping = $this->mapInvoice($invoice2, $qboInvoice2Id);
        $creditNoteMapping = $this->mapCreditNote($creditNote, $qboCreditMemoId);

        // build test payment object
        $invdPayment = new Payment();
        $invdPayment->amount = 10;
        $invdPayment->currency = 'usd';
        $invdPayment->setCustomer(self::$customer);
        $invdPayment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => self::$invoice,
                'amount' => 5,
            ],
            [
                'type' => 'invoice',
                'invoice' => $invoice2,
                'amount' => 5,
            ],
            [
                'type' => 'credit_note',
                'credit_note' => $creditNote->id(),
                'document_type' => 'invoice',
                'invoice' => $invoice2->id(),
                'amount' => 5,
            ],
        ];
        $invdPayment->saveOrFail();

        $expected = [
            'TxnDate' => date('Y-m-d', $invdPayment->date),
            'TotalAmt' => 10.0,
            'PrivateNote' => 'Invoiced ID: '.$invdPayment->id(),
            'CustomerRef' => [
                'value' => $qboCustomerId,
            ],
            'Line' => [
                [
                    'Amount' => 5.0,
                    'LinkedTxn' => [[
                        'TxnType' => 'Invoice',
                        'TxnId' => $qboInvoice1Id,
                    ]],
                ],
                [
                    'Amount' => 10.0,
                    'LinkedTxn' => [[
                        'TxnType' => 'Invoice',
                        'TxnId' => $qboInvoice2Id,
                    ]],
                ],
                [
                    'Amount' => 5.0,
                    'LinkedTxn' => [[
                            'TxnType' => 'CreditMemo',
                            'TxnId' => $qboCreditMemoId,
                    ]],
                ],
            ],
            'PaymentMethodRef' => [
                'value' => $qboPaymentMethodId,
            ],
        ];

        $documentMap = $writer->processDocuments($invdPayment, $qboCustomerId, self::$syncProfile);
        $this->assertEquals($expected, $writer->buildQBOPaymentDetails($invdPayment, $qboCustomerId, $documentMap, self::$syncProfile));

        // delete mappings for future test cases.
        $invoice1Mapping->delete();
        $invoice2Mapping->delete();
        $creditNoteMapping->delete();
    }

    public function testBuildWithConvenienceFee(): void
    {
        $this->enableConvenienceFeeWrites();

        // test (fake) qbo ids
        $qboCustomerId = '1';
        $qboInvoiceId = '1';
        $qboFeeInvoiceId = '2';
        $qboPaymentMethodId = '9';
        $undepositedFundsAccountId = '1';
        $qboItemId = '99';

        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive([
            'getPaymentMethodByName' => $this->getExpectedQBOObject($qboPaymentMethodId),
            'getAccountByName' => $this->getExpectedQBOObject($undepositedFundsAccountId),
            'getInvoiceByNumber' => null,
            'getItemByName' => $this->getExpectedQBOObject($qboItemId),
            'createInvoice' => $this->getExpectedQBOObject($qboFeeInvoiceId),
            'setAccount' => null,
        ]);
        $customerWriter = Mockery::mock(QuickBooksCustomerWriter::class, [$quickbooksApi])->makePartial();
        $creditNoteWriter = Mockery::mock(QuickBooksCreditNoteWriter::class, [$quickbooksApi, $customerWriter])->makePartial();
        $invoiceWriter = Mockery::mock(QuickBooksInvoiceWriter::class, [$quickbooksApi, $customerWriter])->shouldAllowMockingProtectedMethods()->makePartial();
        $writer = $this->getWriter($quickbooksApi, $invoiceWriter, $creditNoteWriter, $customerWriter);

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice->saveOrFail();

        // create fake mappings
        $this->mapInvoice($invoice, $qboInvoiceId);

        // build test payment object
        $invdPayment = new Payment();
        $invdPayment->amount = 10;
        $invdPayment->currency = 'usd';
        $invdPayment->setCustomer(self::$customer);
        $invdPayment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 9,
            ],
            [
                'type' => 'convenience_fee',
                'amount' => 1,
            ],
        ];
        $invdPayment->saveOrFail();

        $expected = [
            'TxnDate' => date('Y-m-d', $invdPayment->date),
            'TotalAmt' => 10.0,
            'PrivateNote' => 'Invoiced ID: '.$invdPayment->id(),
            'CustomerRef' => [
                'value' => $qboCustomerId,
            ],
            'Line' => [
                [
                    'Amount' => 1.0,
                    'LinkedTxn' => [[
                        'TxnType' => 'Invoice',
                        'TxnId' => $qboFeeInvoiceId,
                    ]],
                ],
                [
                    'Amount' => 9.0,
                    'LinkedTxn' => [[
                        'TxnType' => 'Invoice',
                        'TxnId' => $qboInvoiceId,
                    ]],
                ],
            ],
            'PaymentMethodRef' => [
                'value' => $qboPaymentMethodId,
            ],
        ];

        $documentMap = $writer->processDocuments($invdPayment, $qboCustomerId, self::$syncProfile);
        $this->assertEquals($expected, $writer->buildQBOPaymentDetails($invdPayment, $qboCustomerId, $documentMap, self::$syncProfile));

        $this->disableConvenienceFeeWrites();
    }

    public function testCreate(): void
    {
        // build test payment object
        $invdPayment = new Payment();
        $invdPayment->amount = 10;
        $invdPayment->currency = 'usd';
        $invdPayment->setCustomer(self::$customer);
        $invdPayment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => self::$invoice,
                'amount' => 5,
            ],
        ];
        $invdPayment->saveOrFail();

        // set up writer.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $customerWriter = Mockery::mock(QuickBooksCustomerWriter::class, [$quickbooksApi])->makePartial();
        $creditNoteWriter = Mockery::mock(QuickBooksCreditNoteWriter::class, [$quickbooksApi, $customerWriter])->makePartial();
        $invoiceWriter = Mockery::mock(QuickBooksInvoiceWriter::class, [$quickbooksApi, $customerWriter])->shouldAllowMockingProtectedMethods()->makePartial();
        $writer = $this->getWriter($quickbooksApi, $invoiceWriter, $creditNoteWriter, $customerWriter);

        /**
         * Test customer and documents already exist in QBO.
         * No multi-currency.
         * Expects success.
         */
        $customerMapping = $this->mapCustomer(self::$customer, '1');
        $invoiceMapping = $this->mapInvoice(self::$invoice, '1');

        $fakePaymentId = '1';
        $quickbooksApi->shouldReceive([
            'getPaymentMethodByName' => $this->getExpectedQBOObject('1'),
            'createPayment' => $this->getExpectedQBOObject($fakePaymentId),
            'getAccountByName' => $this->getExpectedQBOObject('1'),
            'setAccount' => null,
        ]);

        $writer->create($invdPayment, self::$quickbooksAccount, self::$syncProfile);
        /** @var AccountingPaymentMapping $paymentMapping */
        $paymentMapping = AccountingPaymentMapping::find($invdPayment->id());
        $this->assertNotNull($paymentMapping);
        $this->assertEquals($fakePaymentId, $paymentMapping->accounting_id);
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $paymentMapping->integration_id);
        $this->assertEquals(AccountingPaymentMapping::SOURCE_INVOICED, $paymentMapping->source);

        // clean up mappings
        $customerMapping->delete();
        $invoiceMapping->delete();
        $paymentMapping->delete();

        /**
         * Test customer is written, documents aren't and write_invoices is FALSE.
         * No multi-currency.
         * Expects exception on invoice creation.
         */
        $customerMapping = $this->mapCustomer(self::$customer, '1');

        $quickbooksApi->shouldReceive([
            'getPaymentMethodByName' => $this->getExpectedQBOObject('1'),
            'createPayment' => $this->getExpectedQBOObject($fakePaymentId),
            'setAccount' => null,
        ]);

        $writer->create($invdPayment, self::$quickbooksAccount, self::$syncProfile);
        /** @var ReconciliationError $paymentMapping */
        $error = ReconciliationError::where('object_id', $invdPayment->id())
            ->where('object', 'payment')
            ->oneOrNull();
        $this->assertNotNull($error);
        $this->assertEquals('Unable to write invoice to QuickBooks Online. Writing invoices is disabled.', $error->message);

        // clean up mappings
        $customerMapping->delete();

        /*
         * Test customer is written, documents aren't and write_invoices is TRUE.
         * No multi-currency.
         * Expects success following invoice creation/read.
         * In this case, the invoice writer finds the existing invoice on QBO
         * and creates the mapping.
         */
        $this->enableInvoiceWrites();
        $customerMapping = $this->mapCustomer(self::$customer, '1');

        $quickbooksApi->shouldReceive([
            'getPaymentMethodByName' => $this->getExpectedQBOObject('1'),
            'createPayment' => $this->getExpectedQBOObject($fakePaymentId),
            'getInvoiceByNumber' => $this->getExpectedQBOObject('99'),
            'getAccountByName' => $this->getExpectedQBOObject('1'),
            'setAccount' => null,
        ]);

        $writer->create($invdPayment, self::$quickbooksAccount, self::$syncProfile);
        /** @var AccountingPaymentMapping $paymentMapping */
        $paymentMapping = AccountingPaymentMapping::find($invdPayment->id());
        $this->assertNotNull($paymentMapping);
        $this->assertEquals($fakePaymentId, $paymentMapping->accounting_id);
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $paymentMapping->integration_id);
        $this->assertEquals(AccountingPaymentMapping::SOURCE_INVOICED, $paymentMapping->source);

        /** @var AccountingInvoiceMapping $invoiceMapping */
        $invoiceMapping = AccountingInvoiceMapping::find(self::$invoice->id());
        $this->assertNotNull($invoiceMapping);
        $this->assertEquals(99, $invoiceMapping->accounting_id);
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $invoiceMapping->integration_id);
        $this->assertEquals(AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM, $invoiceMapping->source);

        // clean up mappings
        $customerMapping->delete();
        $paymentMapping->delete();
        $invoiceMapping->delete();
        $this->disableInvoiceWrites();

        /*
         * Test customer is not written to QBO, write_customers is FALSE.
         * No multi-currency.
         * Expects exception on customer creation.
         */
        $writer->create($invdPayment, self::$quickbooksAccount, self::$syncProfile);
        $error = ReconciliationError::where('object_id', $invdPayment->id())->oneOrNull();
        $this->assertNotNull($error);
        $this->assertEquals('Unable to write customer to QuickBooks Online. Writing customers is disabled.', $error->message);

        /*
         * Test write customers and documents, write_customers is TRUE, write_invoices is TRUE
         * No multi-currency.
         * Expects success following customer and invoice creation/read.
         * In this case, the customer and invoice writers find the existing object on QBO
         * and create the mapping.
         */
        $this->enableCustomerWrites();
        $this->enableInvoiceWrites();

        $quickbooksApi->shouldReceive([
            'getPaymentMethodByName' => $this->getExpectedQBOObject('1'),
            'createPayment' => $this->getExpectedQBOObject($fakePaymentId),
            'getCustomerByName' => $this->getExpectedQBOObject('98'),
            'getInvoiceByNumber' => $this->getExpectedQBOObject('99'),
            'getAccountByName' => $this->getExpectedQBOObject('1'),
            'setAccount' => null,
        ]);

        $writer->create($invdPayment, self::$quickbooksAccount, self::$syncProfile);
        /** @var AccountingPaymentMapping $paymentMapping */
        $paymentMapping = AccountingPaymentMapping::find($invdPayment->id());
        $this->assertNotNull($paymentMapping);
        $this->assertEquals($fakePaymentId, $paymentMapping->accounting_id);
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $paymentMapping->integration_id);
        $this->assertEquals(AccountingPaymentMapping::SOURCE_INVOICED, $paymentMapping->source);

        /** @var AccountingCustomerMapping $customerMapping */
        $customerMapping = AccountingCustomerMapping::find(self::$customer->id());
        $this->assertNotNull($customerMapping);
        $this->assertEquals(98, $customerMapping->accounting_id);
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $customerMapping->integration_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_ACCOUNTING_SYSTEM, $customerMapping->source);

        /** @var AccountingInvoiceMapping $invoiceMapping */
        $invoiceMapping = AccountingInvoiceMapping::find(self::$invoice->id());
        $this->assertNotNull($invoiceMapping);
        $this->assertEquals(99, $invoiceMapping->accounting_id);
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $invoiceMapping->integration_id);
        $this->assertEquals(AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM, $invoiceMapping->source);

        // clean up mappings
        $customerMapping->delete();
        $paymentMapping->delete();
        $invoiceMapping->delete();
        $this->disableInvoiceWrites();
        $this->disableCustomerWrites();
    }

    public function testCreateWithConvenienceFee(): void
    {
        $this->enableConvenienceFeeWrites();

        $customer = new Customer();
        $customer->name = 'Sherlock';
        $customer->email = 'sherlock@example.com';
        $customer->address1 = 'Test';
        $customer->address2 = 'Address';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        $invoice->saveOrFail();

        // build test payment object
        $invdPayment = new Payment();
        $invdPayment->amount = 5;
        $invdPayment->currency = 'usd';
        $invdPayment->setCustomer($customer);
        $invdPayment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => $invoice,
                'amount' => 4,
            ],
            [
                'type' => 'convenience_fee',
                'amount' => 1,
            ],
        ];
        $invdPayment->saveOrFail();

        // set up writer.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $customerWriter = Mockery::mock(QuickBooksCustomerWriter::class, [$quickbooksApi])->makePartial();
        $creditNoteWriter = Mockery::mock(QuickBooksCreditNoteWriter::class, [$quickbooksApi, $customerWriter])->makePartial();
        $invoiceWriter = Mockery::mock(QuickBooksInvoiceWriter::class, [$quickbooksApi, $customerWriter])->shouldAllowMockingProtectedMethods()->makePartial();
        $writer = $this->getWriter($quickbooksApi, $invoiceWriter, $creditNoteWriter, $customerWriter);

        $this->mapCustomer($customer, '1');
        $this->mapInvoice($invoice, '1');

        $feeInvoiceId = '99';
        $fakePaymentId = '1';
        $quickbooksApi->shouldReceive([
            'getPaymentMethodByName' => $this->getExpectedQBOObject('1'),
            'createPayment' => $this->getExpectedQBOObject($fakePaymentId),
            'getAccountByName' => $this->getExpectedQBOObject('1'),
            'getInvoiceByNumber' => null,
            'createInvoice' => $this->getExpectedQBOObject($feeInvoiceId),
            'getItemByName' => $this->getExpectedQBOObject('98'),
            'setAccount' => null,
        ]);

        $writer->create($invdPayment, self::$quickbooksAccount, self::$syncProfile);
        /** @var AccountingPaymentMapping $paymentMapping */
        $paymentMapping = AccountingPaymentMapping::find($invdPayment->id());
        $this->assertNotNull($paymentMapping);
        $this->assertEquals($fakePaymentId, $paymentMapping->accounting_id);
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $paymentMapping->integration_id);
        $this->assertEquals(AccountingPaymentMapping::SOURCE_INVOICED, $paymentMapping->source);

        /** @var AccountingConvenienceFeeMapping $feeMapping */
        $feeMapping = AccountingConvenienceFeeMapping::find($invdPayment->id());
        $this->assertNotNull($feeMapping);
        $this->assertEquals($feeInvoiceId, $feeMapping->accounting_id);
        $this->assertEquals(IntegrationType::QuickBooksOnline->value, $feeMapping->integration_id);
        $this->assertEquals(AccountingPaymentMapping::SOURCE_INVOICED, $feeMapping->source);

        $this->disableConvenienceFeeWrites();
    }

    /**
     * @doesNotPerformAssertions
     *
     * Tests that unsupported splits are not reconciled.
     */
    public function testSplits(): void
    {
        // Create test credit note
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

        // setup customer mapping
        $customerMapping = $this->mapCustomer(self::$customer, '1');

        // set up writer.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $quickbooksApi->shouldReceive([
            'getCustomerByName' => $this->getExpectedQBOObject('1'),
            'getPaymentMethodByName' => (object) ['Id' => 'test_payment_method'],
            'setAccount' => null,
        ]);
        $customerWriter = Mockery::mock(QuickBooksCustomerWriter::class, [$quickbooksApi])->makePartial();
        $creditNoteWriter = Mockery::mock(QuickBooksCreditNoteWriter::class, [$quickbooksApi, $customerWriter])->makePartial();
        $invoiceWriter = Mockery::mock(QuickBooksInvoiceWriter::class, [$quickbooksApi, $customerWriter])->shouldAllowMockingProtectedMethods()->makePartial();
        $writer = $this->getWriter($quickbooksApi, $invoiceWriter, $creditNoteWriter, $customerWriter);

        // estimate split
        $estimateSplit = [
            'type' => PaymentItemType::Estimate->value,
            'amount' => 10,
            'estimate' => self::$estimate->id(),
        ];

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 10;
        $payment->currency = 'usd';
        $payment->applied_to = [$estimateSplit];
        $payment->saveOrFail();
        $writer->create($payment, self::$quickbooksAccount, self::$syncProfile);

        // estimate split
        $creditNoteSplit = [
            'type' => PaymentItemType::CreditNote->value,
            'amount' => 10,
            'credit_note' => $creditNote->id(),
        ];

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 10;
        $payment->currency = 'usd';
        $payment->applied_to = [$creditNoteSplit];
        $payment->saveOrFail();
        $writer->create($payment, self::$quickbooksAccount, self::$syncProfile);

        // credit split
        $creditSplit = [
            'type' => PaymentItemType::Credit->value,
            'amount' => 10,
        ];
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 10;
        $payment->currency = 'usd';
        $payment->applied_to = [$creditSplit];
        $payment->saveOrFail();
        $writer->create($payment, self::$quickbooksAccount, self::$syncProfile);

        $customerMapping->delete(); // For future tests.
    }

    public function testNullCustomer(): void
    {
        // set up writer.
        $quickbooksApi = Mockery::mock(QuickBooksApi::class);
        $customerWriter = Mockery::mock(QuickBooksCustomerWriter::class, [$quickbooksApi])->makePartial();
        $creditNoteWriter = Mockery::mock(QuickBooksCreditNoteWriter::class, [$quickbooksApi, $customerWriter])->makePartial();
        $invoiceWriter = Mockery::mock(QuickBooksInvoiceWriter::class, [$quickbooksApi, $customerWriter])->shouldAllowMockingProtectedMethods()->makePartial();
        $writer = $this->getWriter($quickbooksApi, $invoiceWriter, $creditNoteWriter, $customerWriter);

        $invdPayment = new Payment();
        $invdPayment->amount = 10;
        $invdPayment->currency = 'usd';
        $invdPayment->saveOrFail();

        $writer->create($invdPayment, self::$quickbooksAccount, self::$syncProfile);

        // There should be no mapping and no reconciliation error
        $mapping = AccountingPaymentMapping::find($invdPayment->id());
        $error = ReconciliationError::where('object', 'payment')
            ->where('object_id', $invdPayment)
            ->oneOrNull();

        $this->assertNull($mapping);
        $this->assertNull($error);
    }
}
