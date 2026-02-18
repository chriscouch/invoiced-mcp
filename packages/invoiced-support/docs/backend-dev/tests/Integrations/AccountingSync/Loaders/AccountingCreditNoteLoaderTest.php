<?php

namespace App\Tests\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Loaders\AccountingCreditNoteLoader;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\AccountingPaymentItem;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;

class AccountingCreditNoteLoaderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasUnappliedCreditNote();
    }

    private function getLoader(): AccountingCreditNoteLoader
    {
        return self::getService('test.accounting_credit_note_loader');
    }

    public function testLoad(): void
    {
        $record = new AccountingCreditNote(
            integration: IntegrationType::Intacct,
            accountingId: '4567',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'currency' => 'usd',
                'calculate_taxes' => false,
                'number' => 'CN-1012',
                'date' => mktime(6, 0, 0, 6, 14, 2021),
                'due_date' => mktime(18, 0, 0, 2, 11, 2021), // not used
                'purchase_order' => 'PO-12345678901234567890123456789',
                'payment_terms' => 'NET 30',
                'items' => [
                    [
                        'name' => 'Marketing guides',
                        'quantity' => 5,
                        'unit_cost' => 70.0,
                        'metadata' => [],
                    ],
                    [
                        'name' => 'Contract discount test',
                        'quantity' => 1,
                        'unit_cost' => 700.00,
                        'metadata' => [],
                    ],
                ],
                'tax' => 20.0,
                'notes' => 'Testing',
                'metadata' => [
                    'intacct_document_type' => 'Sales Return',
                ],
                'ship_to' => [
                    'name' => 'Bojangle Jones',
                    'address1' => '1234 Main St',
                    'address2' => '',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '78701',
                    'country' => 'US',
                ],
            ],
        );

        $loader = $this->getLoader();

        $result = $loader->load($record);

        /** @var CreditNote $creditNote */
        $creditNote = $result->getModel();
        $this->assertNotNull($creditNote);
        $this->assertTrue($result->wasCreated());
        $this->assertFalse($result->wasUpdated());

        // test values
        $this->assertEquals('CN-1012', $creditNote->number);
        $this->assertEquals(self::$customer->id(), $creditNote->customer);
        $this->assertEquals(1070, $creditNote->total);
        $this->assertEquals(1070, $creditNote->balance);
        $this->assertEquals('PO-12345678901234567890123456789', $creditNote->purchase_order);
        $this->assertEquals('Testing', $creditNote->notes);

        // Repeating the operation should update
        $result = $loader->load($record);
        $this->assertNotNull($result->getModel());
        $this->assertFalse($result->wasCreated());
        $this->assertTrue($result->wasUpdated());
    }

    public function testApplications(): void
    {
        $record = new AccountingCreditNote(
            integration: IntegrationType::Intacct,
            accountingId: '8901',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'date' => mktime(6, 0, 0, 6, 14, 2021),
                'items' => [
                    [
                        'unit_cost' => 100.0,
                    ],
                ],
            ],
        );

        $loader = $this->getLoader();

        $result = $loader->load($record);

        /** @var CreditNote $creditNote */
        $creditNote = $result->getModel();
        $this->assertNotNull($creditNote);
        $this->assertTrue($result->wasCreated());
        $this->assertFalse($result->wasUpdated());

        // Apply to an invoice
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 99]];
        $invoice->saveOrFail();
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => 'credit_note',
                'invoice' => $invoice,
                'credit_note' => $creditNote,
                'document_type' => 'invoice',
                'amount' => 99,
            ],
        ];
        $payment->saveOrFail();

        // Repeating the operation should update and not delete payments
        $result = $loader->load($record);
        $this->assertNotNull($result->getModel());
        $this->assertFalse($result->wasCreated());
        $this->assertTrue($result->wasUpdated());

        $this->assertNotNull(Payment::find($payment->id));
    }

    public function testLoadSourceFromInvoiced(): void
    {
        $mapping = new AccountingCreditNoteMapping();
        $mapping->integration_id = IntegrationType::Intacct->value;
        $mapping->accounting_id = '0891234';
        $mapping->credit_note = self::$creditNote;
        $mapping->source = AccountingCreditNoteMapping::SOURCE_INVOICED;
        $mapping->saveOrFail();

        $record = new AccountingCreditNote(
            integration: IntegrationType::Intacct,
            accountingId: '0891234',
            customer: new AccountingCustomer(
                integration: IntegrationType::Intacct,
                accountingId: '1234',
                values: [
                    'name' => self::$customer->name,
                ],
            ),
            values: [
                'number' => self::$creditNote->number,
                'currency' => 'usd',
                'items' => [['unit_cost' => 500]],
            ],
        );

        $loader = $this->getLoader();
        $result = $loader->load($record);

        $this->assertNotNull($result->getModel());
        $this->assertNull($result->getAction());

        $this->assertEquals(self::$creditNote->id(), $result->getModel()?->id());

        // should NOT update the mapping source
        $this->assertEquals(IntegrationType::Intacct->value, $mapping->refresh()->integration_id);
        $this->assertEquals('0891234', $mapping->accounting_id);
        $this->assertEquals(AccountingCustomerMapping::SOURCE_INVOICED, $mapping->source);
    }

    public function testLoadDeletedCreditNote(): void
    {
        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $mapping = new AccountingCreditNoteMapping();
        $mapping->credit_note = $creditNote;
        $mapping->integration_id = IntegrationType::QuickBooksOnline->value;
        $mapping->accounting_id = 'delete_1';
        $mapping->source = AccountingCreditNoteMapping::SOURCE_ACCOUNTING_SYSTEM;
        $mapping->saveOrFail();

        $loader = $this->getLoader();

        $accountingCreditNote = new AccountingCreditNote(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'delete_1',
            deleted: true
        );

        $result = $loader->load($accountingCreditNote);

        // should delete the credit note
        $this->assertTrue($result->wasDeleted());
        $this->assertNotNull($result->getModel());
        $this->assertNull(CreditNote::find($creditNote->id()));

        $accountingCreditNote2 = new AccountingCreditNote(
            integration: IntegrationType::QuickBooksOnline,
            accountingId: 'delete_2',
            deleted: true
        );

        $result = $loader->load($accountingCreditNote2);

        // should do nothing because the credit note is not existing
        $this->assertNull($result->getAction());
        $this->assertNull($result->getModel());
    }

    public function testReallocateCreditNote(): void
    {
        $customer = new Customer();
        $customer->name = 'Reallocate Test';
        $customer->saveOrFail();

        $invoice1 = new Invoice();
        $invoice1->setCustomer($customer);
        $invoice1->items = [['unit_cost' => 100]];
        $invoice1->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->setCustomer($customer);
        $invoice2->items = [['unit_cost' => 100]];
        $invoice2->saveOrFail();

        $invoice3 = new Invoice();
        $invoice3->setCustomer($customer);
        $invoice3->items = [['unit_cost' => 100]];
        $invoice3->saveOrFail();

        $loader = $this->getLoader();

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: '654234',
            values: [
                'name' => $customer->name,
            ],
        );

        $accountingCreditNote = new AccountingCreditNote(
            integration: IntegrationType::Intacct,
            accountingId: '542345',
            customer: $accountingCustomer,
            values: [
                'number' => 'CN-651654',
                'date' => mktime(6, 0, 0, 6, 14, 2021),
                'items' => [
                    [
                        'unit_cost' => 150,
                    ],
                ],
            ],
            payments: [
                new AccountingPayment(
                    integration: IntegrationType::Intacct,
                    accountingId: '64654',
                    values: [],
                    currency: 'usd',
                    customer: $accountingCustomer,
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: Money::fromDecimal('usd', 100),
                            type: PaymentItemType::CreditNote->value,
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::Intacct,
                                accountingId: '12365',
                                values: ['number' => $invoice1->number],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::Intacct,
                                accountingId: '542345',
                                values: ['number' => 'CN-651654'],
                            ),
                            documentType: 'invoice',
                        ),
                    ],
                ),
                new AccountingPayment(
                    integration: IntegrationType::Intacct,
                    accountingId: '64655',
                    values: [],
                    currency: 'usd',
                    customer: $accountingCustomer,
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: Money::fromDecimal('usd', 50),
                            type: PaymentItemType::CreditNote->value,
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::Intacct,
                                accountingId: '418645',
                                values: ['number' => $invoice2->number],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::Intacct,
                                accountingId: '542345',
                                values: ['number' => 'CN-651654'],
                            ),
                            documentType: 'invoice',
                        ),
                    ],
                ),
            ]
        );

        $result = $loader->load($accountingCreditNote);

        $this->assertTrue($result->wasCreated());

        // Unapply the $50 from Invoice 2 and apply it to Invoice 3
        $accountingCreditNote = new AccountingCreditNote(
            integration: IntegrationType::Intacct,
            accountingId: '542345',
            customer: $accountingCustomer,
            values: [
                'number' => 'CN-651654',
                'date' => mktime(6, 0, 0, 6, 14, 2021),
                'items' => [
                    [
                        'unit_cost' => 150,
                    ],
                ],
            ],
            payments: [
                new AccountingPayment(
                    integration: IntegrationType::Intacct,
                    accountingId: '64656',
                    values: [],
                    currency: 'usd',
                    customer: $accountingCustomer,
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: Money::fromDecimal('usd', 100),
                            type: PaymentItemType::CreditNote->value,
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::Intacct,
                                accountingId: '12365',
                                values: ['number' => $invoice1->number],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::Intacct,
                                accountingId: '542345',
                                values: ['number' => 'CN-651654'],
                            ),
                            documentType: 'invoice',
                        ),
                    ],
                ),
                new AccountingPayment(
                    integration: IntegrationType::Intacct,
                    accountingId: '64657',
                    values: [],
                    currency: 'usd',
                    customer: $accountingCustomer,
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: Money::fromDecimal('usd', 50),
                            type: PaymentItemType::CreditNote->value,
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::Intacct,
                                accountingId: '135186',
                                values: ['number' => $invoice3->number],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::Intacct,
                                accountingId: '542345',
                                values: ['number' => 'CN-651654'],
                            ),
                            documentType: 'invoice',
                        ),
                    ],
                ),
            ],
        );

        $result = $loader->load($accountingCreditNote);

        $this->assertTrue($result->wasUpdated());
    }

    public function testUpdateMappingInfo(): void
    {
        $customer = self::getTestDataFactory()->createCustomer();
        $invoice = self::getTestDataFactory()->createInvoice($customer);
        $creditNote = self::getTestDataFactory()->createCreditNote($customer);
        self::getTestDataFactory()->createCreditNoteTransaction($creditNote, $invoice);

        $loader = $this->getLoader();

        $accountingCreditNote = new AccountingCreditNote(
            integration: IntegrationType::NetSuite,
            accountingId: '654235',
            values: [
                'number' => $creditNote->number,
            ],
        );
        $this->assertNull(AccountingCreditNoteMapping::find($creditNote->id));
        $loader->load($accountingCreditNote);
        $mapping = AccountingCreditNoteMapping::findOrFail($creditNote->id)->toArray();
        unset($mapping['created_at']);
        unset($mapping['updated_at']);
        $this->assertEquals([
            'credit_note_id' => $creditNote->id,
            'accounting_id' => '654235',
            'source' => 'accounting_system',
            'integration_name' => 'NetSuite',
        ], $mapping);
    }

    public function testInvd3875(): void
    {
        $customer = self::getTestDataFactory()->createCustomer();
        $creditNote = self::getTestDataFactory()->createCreditNote($customer);
        $creditNote->taxes = [['amount' => 5]];
        $creditNote->saveOrFail();

        $loader = $this->getLoader();

        $accountingCreditNote = new AccountingCreditNote(
            integration: IntegrationType::Intacct,
            accountingId: '49157899',
            values: [
                'number' => $creditNote->number,
                'ship_to' => [
                    'name' => 'Bojangle Jones',
                    'address1' => '1234 Main St',
                    'address2' => '',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '78701',
                    'country' => 'US',
                ],
            ],
        );

        $loader->load($accountingCreditNote);

        // After the credit note is initially synced, void it on Invoiced and
        // the attempt to resync should not fail
        $creditNote->void();
        $loader->load($accountingCreditNote);

        $this->assertTrue($creditNote->refresh()->voided);
    }

    public function testLoadSyncBalance(): void
    {
        // Scenario: Initial balance sync
        $factory = self::getTestDataFactory();
        $creditNote = $factory->createCreditNote(self::$customer);

        $accountingCustomer = new AccountingCustomer(
            integration: IntegrationType::BusinessCentral,
            accountingId: '1234',
            values: [
                'name' => self::$customer->name,
            ],
        );
        $recordValues = [
            'number' => $creditNote->number,
            'currency' => 'usd',
            'items' => [['unit_cost' => 100]],
        ];
        $record = new AccountingCreditNote(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 9900),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(99, $creditNote->refresh()->balance);
        $this->assertEquals(1, $creditNote->amount_credited);

        // Scenario: Balance decreases after initial sync
        $record = new AccountingCreditNote(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 8000),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(80, $creditNote->refresh()->balance);
        $this->assertEquals(20, $creditNote->amount_credited);

        // Scenario: Balance increases after previous sync
        $record = new AccountingCreditNote(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 9000),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(90, $creditNote->refresh()->balance);
        $this->assertEquals(10, $creditNote->amount_credited);

        // Scenario: Mismatched balance with payment applied
        $payment = $factory->createPayment(self::$customer);
        $invoice = $factory->createInvoice(self::$customer);
        $payment->applied_to = [['type' => 'credit_note', 'credit_note' => $creditNote, 'invoice' => $invoice, 'amount' => 30]];
        $payment->saveOrFail();
        $record = new AccountingCreditNote(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 8000),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(60, $creditNote->refresh()->balance);
        $this->assertEquals(40, $creditNote->amount_credited);

        // Scenario: Paid in full with no other payments applied
        $payment->deleteOrFail();
        $record = new AccountingCreditNote(
            integration: IntegrationType::BusinessCentral,
            accountingId: uniqid(),
            customer: $accountingCustomer,
            values: $recordValues,
            balance: new Money('usd', 0),
        );

        $loader = $this->getLoader();
        $loader->load($record);

        $this->assertEquals(0, $creditNote->refresh()->balance);
        $this->assertEquals(100, $creditNote->amount_credited);
        $this->assertTrue($creditNote->paid);
    }
}
