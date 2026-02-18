<?php

namespace App\Tests\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Loaders\AccountingPaymentLoader;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\AccountingPaymentItem;
use App\Integrations\Enums\IntegrationType;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;

class AccountingPaymentLoaderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    private function getLoader(): AccountingPaymentLoader
    {
        return self::getService('test.accounting_payment_loader');
    }

    public function testRead(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $accountingPayment = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '1234',
            values: [
                'date' => (int) mktime(0, 0, 0, 12, 2, 2016),
                'method' => PaymentMethod::WIRE_TRANSFER,
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '4567',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 100),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1234',
                        values: [
                            'number' => self::$invoice->number,
                        ],
                    ),
                ),
            ],
        );

        $loader = $this->getLoader();

        //
        // Call the method being tested
        //

        $result = $loader->load($accountingPayment);

        //
        // Verify the results
        //

        /** @var Payment $payment */
        $payment = $result->getModel();
        $this->assertInstanceOf(Payment::class, $payment);
        $transactions = $payment->getTransactions();
        $this->assertCount(1, $transactions);
        $transaction = $transactions[0];
        $this->assertInstanceOf(Transaction::class, $transaction);
        $expected = [
            'customer' => self::$customer->id(),
            'invoice' => self::$invoice->id(),
            'credit_note' => null,
            'type' => Transaction::TYPE_PAYMENT,
            'method' => PaymentMethod::WIRE_TRANSFER,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'gateway' => null,
            'gateway_id' => null,
            'parent_transaction' => null,
            'currency' => 'usd',
            'amount' => 1.0,
            'date' => $accountingPayment->values['date'],
            'notes' => null,
            'metadata' => (object) [],
            'estimate' => null,
            'payment_id' => $payment->id(),
        ];

        $arr = $transaction->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        $mapping = AccountingPaymentMapping::where('integration_id', IntegrationType::Intacct->value)
            ->where('accounting_id', '1234')
            ->where('payment_id', $payment->id())
            ->oneOrNull();
        $this->assertInstanceOf(AccountingPaymentMapping::class, $mapping);
        $this->assertEquals(AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM, $mapping->source);
    }

    public function testReadMultipleSplits(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [['unit_cost' => 5]];
        $invoice2->saveOrFail();

        $accountingPayment = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '1235',
            values: [
                'date' => (int) mktime(0, 0, 0, 12, 2, 2016),
                'method' => PaymentMethod::CHECK,
                'reference' => '8034',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '4567',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 100),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1234',
                        values: ['number' => self::$invoice->number],
                    ),
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 500),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: (string) $invoice2->id(),
                        values: ['number' => $invoice2->number],
                    ),
                ),
            ],
        );

        $loader = $this->getLoader();

        //
        // Call the method being tested
        //

        $result = $loader->load($accountingPayment);

        //
        // Verify the results
        //

        /** @var Payment $payment */
        $payment = $result->getModel();
        $transactions = $payment->getTransactions();
        $this->assertCount(2, $transactions);
        $transaction = $transactions[0];
        $this->assertInstanceOf(Transaction::class, $transaction);
        $expected = [
            'customer' => self::$customer->id(),
            'invoice' => self::$invoice->id(),
            'credit_note' => null,
            'type' => Transaction::TYPE_PAYMENT,
            'method' => PaymentMethod::CHECK,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'gateway' => null,
            'gateway_id' => '8034',
            'parent_transaction' => null,
            'currency' => 'usd',
            'amount' => 1.0,
            'date' => $accountingPayment->values['date'],
            'notes' => null,
            'metadata' => (object) [],
            'estimate' => null,
            'payment_id' => $payment->id(),
        ];

        $arr = $transaction->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        $transaction2 = $transactions[1];
        $this->assertInstanceOf(Transaction::class, $transaction2);
        $expected = [
            'customer' => self::$customer->id(),
            'invoice' => $invoice2->id(),
            'credit_note' => null,
            'type' => Transaction::TYPE_PAYMENT,
            'method' => PaymentMethod::CHECK,
            'payment_source' => null,
            'status' => Transaction::STATUS_SUCCEEDED,
            'gateway' => null,
            'gateway_id' => '8034',
            'parent_transaction' => $transaction->id(),
            'currency' => 'usd',
            'amount' => 5.0,
            'date' => $accountingPayment->values['date'],
            'notes' => null,
            'metadata' => (object) [],
            'estimate' => null,
            'payment_id' => $payment->id(),
        ];

        $arr = $transaction2->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        $mapping = AccountingPaymentMapping::where('integration_id', IntegrationType::Intacct->value)
            ->where('accounting_id', '1235')
            ->where('payment_id', $payment->id())
            ->oneOrNull();
        $this->assertInstanceOf(AccountingPaymentMapping::class, $mapping);
        $this->assertEquals(AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM, $mapping->source);
    }

    public function testReadFail(): void
    {
        $this->expectException(LoadException::class);

        //
        // Setup - Models, Mocks, etc.
        //

        $accountingPayment = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '1236',
            values: [
                'date' => (int) mktime(0, 0, 0, 12, 2, 2016),
                'method' => PaymentMethod::CHECK,
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '4567',
                values: ['name' => self::$customer->name],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', -1),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1234',
                        values: ['number' => self::$invoice->number],
                    ),
                ),
            ],
        );

        $loader = $this->getLoader();

        //
        // Call the method being tested
        //

        $loader->load($accountingPayment);
    }

    public function testReadCreditNoteApplications(): void
    {
        // Create test data
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

        $accountingPayment = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '4321',
            values: [
                'date' => (int) mktime(0, 0, 0, 1, 14, 2021),
                'method' => PaymentMethod::OTHER,
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '4567',
                values: ['name' => self::$customer->name],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: Money::fromDecimal('usd', 15),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: (string) $invoice->id(),
                        values: ['number' => $invoice->number],
                    ),
                ),
                new AccountingPaymentItem(
                    amount: Money::fromDecimal('usd', 10),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: (string) $invoice->id(),
                        values: ['number' => $invoice->number],
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: (string) $creditNote->id(),
                        values: ['number' => $creditNote->number],
                    ),
                    documentType: 'invoice',
                ),
            ],
        );

        // Test reconciliation
        $loader = $this->getLoader();
        $result = $loader->load($accountingPayment);
        /** @var Payment $payment */
        $payment = $result->getModel();
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(2, count($payment->applied_to));
        $this->assertEquals(15, $payment->amount);
        $this->assertEquals(90, $creditNote->refresh()->balance);
        $this->assertEquals(75, $invoice->refresh()->balance);

        $expectedSplits = [
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => 15.0,
                'invoice' => $invoice->id(),
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'amount' => 10.0,
                'invoice' => $invoice->id(),
                'credit_note' => $creditNote->id(),
                'document_type' => 'invoice',
            ],
        ];

        $returnedSplits = array_map(function ($split) {
            unset($split['id']); // Don't have access to the transaction id's; not testing for them.

            return $split;
        }, $payment->applied_to);

        $this->assertEquals($expectedSplits, $returnedSplits);
    }

    public function testReadUpdate(): void
    {
        // Create test data
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

        $accountingPayment = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '9898',
            values: [
                'date' => (int) mktime(0, 0, 0, 1, 14, 2021),
                'method' => PaymentMethod::OTHER,
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '4567',
                values: ['name' => self::$customer->name],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: Money::fromDecimal('usd', 15),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: (string) $invoice->id(),
                        values: ['number' => $invoice->number],
                    ),
                ),
            ],
        );

        $loader = $this->getLoader();

        // Create the payment, change the splits, and test read update.
        $result = $loader->load($accountingPayment);
        /** @var Payment $payment */
        $payment = $result->getModel();
        $this->assertEquals(85, $invoice->refresh()->balance);
        $this->assertEquals(15, $payment->refresh()->amount);

        $accountingPayment = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '9898',
            values: [
                'date' => (int) mktime(0, 0, 0, 1, 14, 2021),
                'method' => PaymentMethod::OTHER,
                'notes' => 'Updated notes',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '4567',
                values: ['name' => self::$customer->name],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: Money::fromDecimal('usd', 25), // updated amount
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: (string) $invoice->id(),
                        values: ['number' => $invoice->number],
                    ),
                ),
            ],
        );

        $loader->load($accountingPayment);
        $payment->refresh();

        $this->assertEquals(75, $invoice->refresh()->balance);
        $this->assertEquals('Updated notes', $payment->notes);
        $this->assertEquals(25, $payment->amount);
    }

    /**
     * INVD-2541.
     */
    public function testCreditApplications(): void
    {
        // build invoice and credit note mappings
        $customer = $this->buildCustomer('C-191919');
        $inv1 = $this->buildMappedInvoice($customer, 50, '1991');
        $inv2 = $this->buildMappedInvoice($customer, 150, '1992');
        $inv3 = $this->buildMappedInvoice($customer, 50, '1993');
        $cn1 = $this->buildMappedCreditNote($customer, 100, '1994');
        $cn2 = $this->buildMappedCreditNote($customer, 100, '1995');

        $record = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '0000',
            values: [
                'date' => (int) mktime(6, 0, 0, 9, 1, 2021),
                'method' => 'check',
                'reference' => 'P-0000',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '',
                values: ['name' => 'Test Customer', 'number' => 'C-191919'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1991',
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '1994',
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1992',
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '1994',
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 10000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1992',
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '1995',
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1993',
                    ),
                ),
            ]
        );

        $loader = $this->getLoader();

        $result = $loader->load($record);

        /** @var Payment $importedPayment */
        $importedPayment = $result->getModel();
        $this->assertInstanceOf(Payment::class, $importedPayment);
        $this->assertTrue($result->wasCreated());
        $this->assertFalse($result->wasUpdated());

        $this->assertEquals(50, $importedPayment->amount);
        $appliedTo = $importedPayment->applied_to;
        foreach ($appliedTo as &$apply) {
            unset($apply['id']); // unset transaction ids to test payment application
        }

        $expected = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => $inv1->id(),
                'credit_note' => $cn1->id(),
                'amount' => 50,
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => $inv2->id(),
                'credit_note' => $cn1->id(),
                'amount' => 50,
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => $inv2->id(),
                'credit_note' => $cn2->id(),
                'amount' => 100,
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $inv3->id(),
                'amount' => 50,
            ],
        ];

        $this->assertEquals($expected, $appliedTo);
        $mapping = AccountingPaymentMapping::where('payment_id', $importedPayment->id())
            ->where('accounting_id', '0000')
            ->where('integration_id', IntegrationType::Intacct->value)
            ->where('source', AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM)
            ->oneOrNull();
        $this->assertInstanceOf(AccountingPaymentMapping::class, $mapping);
    }

    private function buildCustomer(string $number): Customer
    {
        $customer = Customer::where('number', $number)->oneOrNull();
        if ($customer instanceof Customer) {
            return $customer;
        }

        $customer = new Customer();
        $customer->number = $number;
        $customer->name = 'Customer '.$number;
        $customer->email = "customer$number@example.com";
        $customer->saveOrFail();

        return $customer;
    }

    private function buildMappedInvoice(Customer $customer, float $amount, string $accountingId): Invoice
    {
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => $amount,
            ],
        ];
        $invoice->saveOrFail();

        $mapping = new AccountingInvoiceMapping();
        $mapping->accounting_id = $accountingId;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->source = AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->invoice = $invoice;
        $mapping->saveOrFail();

        return $invoice;
    }

    private function buildMappedCreditNote(Customer $customer, float $amount, string $accountingId): CreditNote
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer($customer);
        $creditNote->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => $amount,
            ],
        ];
        $creditNote->saveOrFail();

        $mapping = new AccountingCreditNoteMapping();
        $mapping->accounting_id = $accountingId;
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->source = AccountingCreditNoteMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->credit_note = $creditNote;
        $mapping->saveOrFail();

        return $creditNote;
    }

    /**
     * INVD-2570.
     */
    public function testOnlyCreditNotes(): void
    {
        // build invoice and credit note mappings
        $customer = $this->buildCustomer('C-191919');
        $inv1 = $this->buildMappedInvoice($customer, 50, '2991');
        $inv2 = $this->buildMappedInvoice($customer, 150, '2992');
        $cn1 = $this->buildMappedCreditNote($customer, 50, '2993');
        $cn2 = $this->buildMappedCreditNote($customer, 150, '2994');

        $record = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '0002',
            values: [
                'date' => (int) mktime(6, 0, 0, 9, 1, 2021),
                'method' => 'other',
                'reference' => 'P-0002',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '',
                values: ['name' => 'Test Customer', 'number' => 'C-191919'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '2991',
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '2993',
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 15000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '2992',
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::Intacct,
                        accountingId: '2994',
                    ),
                    documentType: 'invoice',
                ),
            ],
            voided: false,
        );

        $loader = $this->getLoader();

        $result = $loader->load($record);

        /** @var Payment $importedPayment */
        $importedPayment = $result->getModel();
        $this->assertInstanceOf(Payment::class, $importedPayment);
        $this->assertTrue($result->wasCreated());
        $this->assertFalse($result->wasUpdated());

        $this->assertEquals(PaymentMethod::OTHER, $importedPayment->method);
        $this->assertEquals(0, $importedPayment->amount);
        $appliedTo = $importedPayment->applied_to;
        foreach ($appliedTo as &$apply) {
            unset($apply['id']); // unset transaction ids to test payment application
        }

        $expected = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => $inv1->id(),
                'credit_note' => $cn1->id(),
                'amount' => 50,
            ],
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => $inv2->id(),
                'credit_note' => $cn2->id(),
                'amount' => 150,
            ],
        ];

        $this->assertEquals($expected, $appliedTo);
        /** @var AccountingPaymentMapping $mapping */
        $mapping = AccountingPaymentMapping::where('payment_id', $importedPayment->id())
            ->where('accounting_id', '0002')
            ->where('integration_id', IntegrationType::Intacct->value)
            ->where('source', AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM)
            ->oneOrNull();
        $this->assertInstanceOf(AccountingPaymentMapping::class, $mapping);
    }

    public function testVoid(): void
    {
        $originalPayment = new Payment();
        $originalPayment->amount = 100;
        $originalPayment->saveOrFail();
        $mapping = new AccountingPaymentMapping();
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = 'void_test';
        $mapping->payment = $originalPayment;
        $mapping->source = AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $record = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: 'void_test',
            currency: 'usd',
            voided: true
        );

        $loader = $this->getLoader();

        $result = $loader->load($record);

        /** @var Payment $importedPayment */
        $importedPayment = $result->getModel();
        $this->assertInstanceOf(Payment::class, $importedPayment);
        $this->assertFalse($result->wasCreated());
        $this->assertFalse($result->wasUpdated());
        $this->assertTrue($result->wasDeleted());
        $this->assertTrue($importedPayment->voided);
        $this->assertEquals($originalPayment->id, $importedPayment->id);
    }

    public function testReadSameInvoice(): void
    {
        // build invoice and credit note mappings
        $customer = $this->buildCustomer('C-212121');
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 150]];
        $invoice->saveOrFail();
        $creditNote = new CreditNote();
        $creditNote->setCustomer($customer);
        $creditNote->items = [['unit_cost' => 50]];
        $creditNote->saveOrFail();

        $record = new AccountingPayment(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: '0005',
            values: [
                'date' => (int) mktime(6, 0, 0, 9, 1, 2021),
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::QuickBooksOnline,
                accountingId: '',
                values: ['number' => 'C-212121'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 5000),
                    type: PaymentItemType::CreditNote->value,
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '089234',
                        values: ['number' => $invoice->number],
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '092435',
                        values: ['number' => $creditNote->number],
                    ),
                    documentType: 'invoice',
                ),
                new AccountingPaymentItem(
                    amount: new Money('usd', 1000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::QuickBooksOnline,
                        accountingId: '089234',
                        values: ['number' => $invoice->number],
                    ),
                ),
            ],
            voided: false,
        );

        $loader = $this->getLoader();

        $result = $loader->load($record);

        /** @var Payment $importedPayment */
        $importedPayment = $result->getModel();
        $this->assertInstanceOf(Payment::class, $importedPayment);
        $this->assertTrue($result->wasCreated());
        $this->assertFalse($result->wasUpdated());

        $this->assertEquals(90, $invoice->refresh()->balance);
        $this->assertEquals(0, $creditNote->refresh()->balance);

        $this->assertEquals(PaymentMethod::OTHER, $importedPayment->method);
        $this->assertEquals(10, $importedPayment->amount);
        $appliedTo = $importedPayment->applied_to;
        foreach ($appliedTo as &$apply) {
            unset($apply['id']); // unset transaction ids to test payment application
        }

        $expected = [
            [
                'type' => PaymentItemType::CreditNote->value,
                'document_type' => 'invoice',
                'invoice' => $invoice->id(),
                'credit_note' => $creditNote->id(),
                'amount' => 50,
            ],
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice->id(),
                'amount' => 10,
            ],
        ];

        $this->assertEquals($expected, $appliedTo);
        /** @var AccountingPaymentMapping $mapping */
        $mapping = AccountingPaymentMapping::where('payment_id', $importedPayment->id())
            ->where('accounting_id', '0005')
            ->where('integration_id', IntegrationType::QuickBooksOnline->value)
            ->where('source', AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM)
            ->oneOrNull();
        $this->assertInstanceOf(AccountingPaymentMapping::class, $mapping);
    }

    public function testLoadSourceFromInvoiced(): void
    {
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 200;
        $payment->saveOrFail();

        $mapping = new AccountingPaymentMapping();
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '0891234';
        $mapping->payment = $payment;
        $mapping->source = AccountingPaymentMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $record = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '0891234',
            values: [
                'date' => (int) mktime(6, 0, 0, 9, 1, 2021),
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 1000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '089234',
                        values: ['number' => self::$invoice->number],
                    ),
                ),
            ],
            voided: false,
        );

        $loader = $this->getLoader();
        $result = $loader->load($record);

        $this->assertNotNull($result->getModel());
        $this->assertNull($result->getAction());

        $this->assertEquals($payment->id(), $result->getModel()?->id());

        // should NOT update the mapping source
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->refresh()->integration_id);
        $this->assertEquals('0891234', $mapping->accounting_id);
        $this->assertEquals(AccountingPaymentMapping::SOURCE_INVOICED, $mapping->source);
    }

    public function testLoadDeletedPayment(): void
    {
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 100;
        $payment->saveOrFail();

        $mapping = new AccountingPaymentMapping();
        $mapping->payment = $payment;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->accounting_id = 'delete_1';
        $mapping->source = AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $loader = $this->getLoader();

        $accountingPayment = new AccountingPayment(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'delete_1',
            deleted: true
        );

        $result = $loader->load($accountingPayment);

        // should delete the payment
        $this->assertTrue($result->wasDeleted());
        $this->assertNotNull($result->getModel());
        $this->assertNull(Payment::find($payment->id()));

        $accountingPayment2 = new AccountingPayment(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'delete_2',
            deleted: true
        );

        $result = $loader->load($accountingPayment2);

        // should do nothing because the payment is not existing
        $this->assertNull($result->getAction());
        $this->assertNull($result->getModel());
    }

    public function testLoadNotSynced(): void
    {
        $record = new AccountingPayment(
            integration: IntegrationType::Intacct,
            accountingId: '973457',
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '8904',
                values: [
                    'name' => 'INVD-3163',
                ],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 1000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::Intacct,
                        accountingId: '1231654',
                        values: ['number' => 'INVD-DOESNOTEXIST'],
                    ),
                ),
            ],
            voided: false,
        );

        $loader = $this->getLoader();
        $result = $loader->load($record);

        $this->assertNull($result->getModel());
        $this->assertNull($result->getAction());

        // Should NOT create a customer
        $this->assertEquals(0, Customer::where('name', 'INVD-3163')->count());
    }
}
