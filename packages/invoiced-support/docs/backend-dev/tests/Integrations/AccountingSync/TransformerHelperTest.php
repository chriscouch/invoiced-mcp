<?php

namespace App\Tests\Integrations\AccountingSync;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\IntegrationConfiguration;
use App\Integrations\AccountingSync\Models\AccountingWritableModel;
use App\Integrations\AccountingSync\ReadSync\TransformerHelper;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\AccountingJsonRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\AccountingPaymentItem;
use App\Integrations\AccountingSync\ValueObjects\AccountingXmlRecord;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;
use App\Integrations\AccountingSync\ValueObjects\TransformField;
use App\Integrations\Enums\IntegrationType;
use App\SubscriptionBilling\Models\Subscription;
use App\Tests\AppTestCase;
use SimpleXMLElement;

class TransformerHelperTest extends AppTestCase
{
    public function testMappingConfiguration(): void
    {
        $config = IntegrationConfiguration::get(false);
        foreach ($config->all() as $integration => $dataFlows) {
            foreach ($dataFlows as $dataFlow => $data) {
                $mapping = $config->getMapping(IntegrationType::fromString($integration), $dataFlow);
                $this->assertGreaterThan(0, count($mapping));
            }
        }
    }

    public function testTransformJson(): void
    {
        $mapping = [
            new TransformField('id', 'accounting_id'),
            new TransformField('number', 'number'),
            new TransformField('invoiceDate', 'date', TransformFieldType::DateUnix),
            new TransformField('dueDate', 'due_date', TransformFieldType::DateUnix),
            new TransformField('customerPurchaseOrderReference', 'purchase_order'),
            new TransformField('currencyCode', 'currency', TransformFieldType::Currency),
            new TransformField('salesperson', 'metadata/salesperson'),
            new TransformField('pdf', 'pdf', documentContext: false),
            // Customer
            new TransformField('customerId', 'customer/accounting_id'),
            new TransformField('customerNumber', 'customer/number'),
            new TransformField('customerName', 'customer/name'),
            // Ship To
            new TransformField('shipToName', 'ship_to/name'),
            new TransformField('shipToContact', 'ship_to/attention_to'),
            new TransformField('shipToAddressLine1', 'ship_to/address1'),
            new TransformField('shipToAddressLine2', 'ship_to/address2'),
            new TransformField('shipToCity', 'ship_to/city'),
            new TransformField('shipToCountry', 'ship_to/country'),
            new TransformField('shipToState', 'ship_to/state'),
            new TransformField('shipToPostCode', 'ship_to/postal_code'),
            // Line Items
            new TransformField('salesInvoiceLines[]/description', 'items[]/name'),
            new TransformField('salesInvoiceLines[]/description2', 'items[]/description'),
            new TransformField('salesInvoiceLines[]/quantity', 'items[]/quantity', TransformFieldType::Float),
            new TransformField('salesInvoiceLines[]/unitPrice', 'items[]/unit_cost', TransformFieldType::Float),
            new TransformField('salesInvoiceLines[]/quantity', 'items[]/quantity', TransformFieldType::Float),
            // Bad Mapping
            new TransformField('doesNotExist[]/test', 'does_not_exist[]/test'),
            // Fixed Value
            new TransformField('__value__', 'metadata/fixed_value', value: 'Test'),
        ];

        $input = json_decode((string) file_get_contents(__DIR__.'/data/test_invoice.json'));
        $supportingInput = json_decode((string) file_get_contents(__DIR__.'/data/test_invoice_supporting.json'));
        $record = new AccountingJsonRecord($input, $supportingInput);
        $expected = [
            'accounting_id' => '1c324dba-9c8e-ee11-be3f-6045bde9b4bf',
            'number' => 'PS-INV103012',
            'date' => 1643414400,
            'due_date' => 1643414400,
            'purchase_order' => '',
            'currency' => 'usd',
            'metadata' => [
                'salesperson' => 'JO',
                'fixed_value' => 'Test',
            ],
            'pdf' => 'pdf',
            'customer' => [
                'accounting_id' => '37e8cbaf-9c8e-ee11-be3f-6045bde9b4bf',
                'number' => '30000',
                'name' => 'School of Fine Art',
            ],
            'ship_to' => [
                'name' => 'School of Fine Art',
                'attention_to' => 'Meagan Bond',
                'address1' => '10 High Tower Green',
                'address2' => '',
                'city' => 'Miami',
                'country' => 'US',
                'state' => 'FL',
                'postal_code' => '37125',
            ],
            'items' => [
                [
                    'name' => 'ATHENS Desk',
                    'description' => '',
                    'quantity' => 4.0,
                    'unit_cost' => 1000.8,
                ],
                [
                    'name' => 'BERLIN Guest Chair, yellow',
                    'description' => '',
                    'quantity' => 8.0,
                    'unit_cost' => 192.8,
                ],
                [
                    'name' => 'MUNICH Swivel Chair, yellow',
                    'description' => '',
                    'quantity' => 5.0,
                    'unit_cost' => 190.1,
                ],
                [
                    'name' => 'ATLANTA Whiteboard, base',
                    'description' => '',
                    'quantity' => 3.0,
                    'unit_cost' => 1397.3,
                ],
            ],
        ];
        $this->assertEquals($expected, TransformerHelper::transformJson($record, $mapping));
    }

    public function testTransformXml(): void
    {
        $mapping = [
            new TransformField('PRRECORDKEY', 'accounting_id'),
            new TransformField('DOCNO', 'number'),
            new TransformField('WHENPOSTED', 'date', TransformFieldType::DateUnix),
            new TransformField('WHENDUE', 'due_date', TransformFieldType::DateUnix),
            new TransformField('PONUMBER', 'purchase_order'),
            new TransformField('CURRENCY', 'currency', TransformFieldType::Currency),
            new TransformField('CONTRACTID', 'metadata/contractid'),
            new TransformField('TESTBOOL', 'metadata/boolean', TransformFieldType::Boolean),
            // Customer
            new TransformField('CUSTREC', 'customer/accounting_id'),
            new TransformField('CUSTVENDID', 'customer/number'),
            new TransformField('CUSTVENDNAME', 'customer/name'),
            // Ship To
            new TransformField('SHIPTO/PRINTAS', 'ship_to/name'),
            new TransformField('SHIPTO/MAILADDRESS/ADDRESS1', 'ship_to/address1'),
            new TransformField('SHIPTO/MAILADDRESS/ADDRESS2', 'ship_to/address2', xmlEmptyAsNull: false),
            new TransformField('SHIPTO/MAILADDRESS/CITY', 'ship_to/city'),
            new TransformField('SHIPTO/MAILADDRESS/COUNTRYCODE', 'ship_to/country'),
            new TransformField('SHIPTO/MAILADDRESS/STATE', 'ship_to/state'),
            new TransformField('SHIPTO/MAILADDRESS/ZIP', 'ship_to/postal_code'),
            // Line Items
            new TransformField('SODOCUMENTENTRIES/sodocumententry[]/ITEMDESC', 'items[]/name'),
            new TransformField('SODOCUMENTENTRIES/sodocumententry[]/MULTIPLIER', 'items[]/metadata/multiplier'),
            new TransformField('SODOCUMENTENTRIES/sodocumententry[]/QUANTITY', 'items[]/quantity', TransformFieldType::Float),
            new TransformField('SODOCUMENTENTRIES/sodocumententry[]/TRX_PRICE', 'items[]/unit_cost', TransformFieldType::Float),
            // Bad Mapping
            new TransformField('doesNotExist[]/test', 'does_not_exist[]/test'),
            // Fixed Value
            new TransformField('__value__', 'metadata/fixed_value', value: 'Test'),
        ];

        /** @var SimpleXMLElement $input */
        $input = simplexml_load_string((string) file_get_contents(__DIR__.'/data/test_invoice.xml'));
        $record = new AccountingXmlRecord($input);
        $expected = [
            'accounting_id' => '4567',
            'number' => 'INV-1012',
            'date' => 1484179200,
            'due_date' => 1486771200,
            'purchase_order' => 'PO-123456789012345678901234567890',
            'currency' => 'usd',
            'metadata' => [
                'contractid' => '483',
                'boolean' => false,
                'fixed_value' => 'Test',
            ],
            'customer' => [
                'accounting_id' => '2',
                'number' => 'CUST-0002',
                'name' => 'Test 2',
            ],
            'ship_to' => [
                'name' => 'Bojangle Jones',
                'address1' => '1234 Main St',
                'address2' => '',
                'city' => 'Austin',
                'country' => 'US',
                'state' => 'TX',
                'postal_code' => '78701',
            ],
            'items' => [
                [
                    'name' => 'Marketing guides:',
                    'quantity' => 5.5,
                    'unit_cost' => 144.0,
                    'metadata' => [
                        'multiplier' => '1',
                    ],
                ],
                [
                    'name' => 'Contract discount test',
                    'quantity' => 10.1234,
                    'unit_cost' => 144.0,
                    'metadata' => [
                        'multiplier' => '2',
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, TransformerHelper::transformXml($record, $mapping));
    }

    /**
     * @dataProvider provideTransformValueFields
     */
    public function testTransformValue(TransformField $field, mixed $input, mixed $expected): void
    {
        $this->assertEquals($expected, TransformerHelper::transformValue($field, $input));
    }

    public function provideTransformValueFields(): array
    {
        return [
            // String fields
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::String), 'test', 'test'],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::String), ['test' => true], '{"test":true}'],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::String), (object) ['test' => true], '{"test":true}'],
            // Float Fields
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Float), 1.23, 1.23],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Float), '1.23', 1.23],
            // Unix Timestamp Fields
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::DateUnix, timeOfDay: 6), '2024-01-09T00:00:00', 1704780000],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::DateUnix, timeOfDay: 18), '2024-01-09T00:00:00', 1704823200],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::DateUnix), '2024-02-08T00:00:00', 1707350400],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::DateUnix), '2023-12-14', 1702512000],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::DateUnix), '2008-09-22T14:01:54.9571247Z', 1222041600],
            // Boolean Fields
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Boolean), 'YES', true],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Boolean), 'true', true],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Boolean), '1', true],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Boolean), 'false', false],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Boolean), '0', false],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Boolean), '', false],
            // Array Fields
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Array), 'test1,test2,test3', ['test1', 'test2', 'test3']],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Array), ['test1', 'test2', 'test3'], ['test1', 'test2', 'test3']],
            // Currency Fields
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Currency), 'USD', 'usd'],
            // Country Fields
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Country), 'US', 'US'],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Country), 'AA', null],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Country), 'USA', 'US'],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Country), 'AAA', null],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Country), 'United States', 'US'],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::Country), 'Not A Country', null],
            // Email List Fields
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::EmailList), '', []],
            [new TransformField(sourceField: '', destinationField: '', type: TransformFieldType::EmailList), 'test@example.com,test2@example.com;test3@example.com', ['test@example.com', 'test2@example.com', 'test3@example.com']],
        ];
    }

    public function testMakeCustomer(): void
    {
        $input = [
            'accounting_system' => 'netsuite',
            'accounting_id' => '1234',
            'parent_customer' => [
                'accounting_id' => '456',
                'name' => 'Parent Customer',
            ],
            'number' => 'CUST-00001',
            'name' => 'My Customer',
            'emails' => ['test@example.com', 'test2@example.com'],
            'contacts' => [
                [
                    'name' => 'Bob Loblaw',
                    'email' => 'bob@example.com',
                ],
            ],
            'deleted' => true,
        ];

        $expected = new AccountingCustomer(
            integration: IntegrationType::NetSuite,
            accountingId: '1234',
            values: [
                'name' => 'My Customer',
                'number' => 'CUST-00001',
            ],
            emails: ['test@example.com', 'test2@example.com'],
            contacts: [
                [
                    'name' => 'Bob Loblaw',
                    'email' => 'bob@example.com',
                ],
            ],
            parentCustomer: new AccountingCustomer(
                integration: IntegrationType::NetSuite,
                accountingId: '456',
                values: ['name' => 'Parent Customer'],
            ),
            deleted: true,
        );

        $accountingDocument = TransformerHelper::makeCustomer(IntegrationType::NetSuite, $input);
        $this->assertEquals($expected, $accountingDocument);
    }

    public function testMakeCustomer2(): void
    {
        $input = [
            'accounting_system' => 'netsuite',
            'metadata' => [
                'subsidiary' => 'Honeycomb Holdings Inc.',
            ],
            'accounting_id' => '2191',
            'type' => 'company',
            'number' => '1660',
            'name' => '1660',
            'active' => true,
            'phone' => null,
            'tax_id' => null,
            'payment_terms' => null,
            'contacts' => [],
            'notes' => '',
            'bill_to_parent' => false,
            'parent_customer' => [
                'accounting_system' => 'netsuite',
                'metadata' => [
                    'subsidiary' => 'Honeycomb Holdings Inc.',
                    'single_select_test' => '',
                    'payment_commitment_date' => '',
                ],
                'accounting_id' => '2190',
                'type' => 'company',
                'number' => '1661',
                'name' => '1661',
                'active' => true,
                'phone' => null,
                'tax_id' => null,
                'payment_terms' => 'Net 30',
                'contacts' => [],
                'notes' => '',
                'autopay' => false,
                'bill_to_parent' => false,
            ],
        ];

        $expected = new AccountingCustomer(
            integration: IntegrationType::NetSuite,
            accountingId: '2191',
            values: [
                'metadata' => [
                    'subsidiary' => 'Honeycomb Holdings Inc.',
                ],
                'type' => 'company',
                'number' => '1660',
                'name' => '1660',
                'active' => true,
                'phone' => null,
                'tax_id' => null,
                'payment_terms' => null,
                'notes' => '',
                'bill_to_parent' => false,
            ],
            emails: null,
            contacts: [],
            parentCustomer: new AccountingCustomer(
                integration: IntegrationType::NetSuite,
                accountingId: '2190',
                values: [
                    'metadata' => [
                        'subsidiary' => 'Honeycomb Holdings Inc.',
                        'single_select_test' => '',
                        'payment_commitment_date' => '',
                    ],
                    'type' => 'company',
                    'number' => '1661',
                    'name' => '1661',
                    'active' => true,
                    'phone' => null,
                    'tax_id' => null,
                    'payment_terms' => 'Net 30',
                    'notes' => '',
                    'autopay' => false,
                    'bill_to_parent' => false,
                ],
                contacts: [],
            ),
            deleted: false,
        );

        $accountingDocument = TransformerHelper::makeCustomer(IntegrationType::NetSuite, $input);
        $this->assertEquals($expected, $accountingDocument);
    }

    public function testMakeInvoice(): void
    {
        $input = [
            'accounting_system' => 'netsuite',
            'accounting_id' => '1234',
            'customer' => [
                'accounting_id' => '456',
                'name' => 'My Customer',
            ],
            'voided' => true,
            'deleted' => true,
            'number' => 'INV-00001',
            'items' => [
                [
                    'name' => 'Test Item',
                    'quantity' => 5,
                    'unit_cost' => 400,
                ],
            ],
            'pdf' => 'pdf contents',
            'installments' => [
                [
                    'amount' => 400,
                    'date' => 123456,
                ],
            ],
            'delivery' => [
                'emails' => 'test@test.com',
                'cadence_id' => 123456,
            ],
            'balance' => 1234.56,
        ];

        $expected = new AccountingInvoice(
            integration: IntegrationType::NetSuite,
            accountingId: '1234',
            customer: new AccountingCustomer(
                integration: IntegrationType::NetSuite,
                accountingId: '456',
                values: ['name' => 'My Customer'],
            ),
            values: [
                'number' => 'INV-00001',
                'items' => [
                    [
                        'name' => 'Test Item',
                        'quantity' => 5,
                        'unit_cost' => 400,
                    ],
                ],
            ],
            voided: true,
            pdf: 'pdf contents',
            installments: [
                [
                    'amount' => 400,
                    'date' => 123456,
                ],
            ],
            deleted: true,
            delivery: [
                'emails' => 'test@test.com',
                'cadence_id' => 123456,
            ],
            balance: new Money('usd', 123456),
        );

        $accountingDocument = TransformerHelper::makeInvoice(IntegrationType::NetSuite, $input, self::$company);
        $this->assertEquals($expected, $accountingDocument);
    }

    public function testMakeCreditNote(): void
    {
        $input = [
            'accounting_system' => 'netsuite',
            'accounting_id' => '1234',
            'customer' => [
                'accounting_id' => '456',
                'name' => 'My Customer',
            ],
            'voided' => true,
            'deleted' => true,
            'number' => 'CN-00001',
            'items' => [
                [
                    'name' => 'Test Item',
                    'quantity' => 5,
                    'unit_cost' => 400,
                ],
            ],
            'pdf' => 'pdf contents',
            'payments' => [
                [
                    'accounting_id' => '789',
                    'customer' => [
                        'name' => 'My Customer',
                    ],
                    'applied_to' => [
                        [
                            'amount' => 400,
                            'invoice' => [
                                'number' => 'INV-00001',
                            ],
                            'credit_note' => [
                                'number' => 'CN-00001',
                            ],
                            'document_type' => 'invoice',
                        ],
                    ],
                ],
            ],
            'balance' => 1234.56,
        ];

        $expected = new AccountingCreditNote(
            integration: IntegrationType::NetSuite,
            accountingId: '1234',
            customer: new AccountingCustomer(
                integration: IntegrationType::NetSuite,
                accountingId: '456',
                values: ['name' => 'My Customer'],
            ),
            values: [
                'number' => 'CN-00001',
                'items' => [
                    [
                        'name' => 'Test Item',
                        'quantity' => 5,
                        'unit_cost' => 400,
                    ],
                ],
            ],
            voided: true,
            pdf: 'pdf contents',
            payments: [
                new AccountingPayment(
                    integration: IntegrationType::NetSuite,
                    accountingId: '789',
                    currency: 'usd',
                    customer: new AccountingCustomer(
                        integration: IntegrationType::NetSuite,
                        accountingId: '',
                        values: ['name' => 'My Customer'],
                    ),
                    appliedTo: [
                        new AccountingPaymentItem(
                            amount: new Money('usd', 40000),
                            invoice: new AccountingInvoice(
                                integration: IntegrationType::NetSuite,
                                accountingId: '',
                                values: ['number' => 'INV-00001'],
                            ),
                            creditNote: new AccountingCreditNote(
                                integration: IntegrationType::NetSuite,
                                accountingId: '',
                                values: ['number' => 'CN-00001'],
                            ),
                            documentType: 'invoice',
                        ),
                    ],
                ),
            ],
            deleted: true,
            balance: new Money('usd', 123456),
        );

        $accountingDocument = TransformerHelper::makeCreditNote(IntegrationType::NetSuite, $input, new Company(['currency' => 'usd']));
        $this->assertEquals($expected, $accountingDocument);
    }

    public function testMakePayment(): void
    {
        $input = [
            'accounting_system' => 'netsuite',
            'accounting_id' => '789',
            'customer' => [
                'name' => 'My Customer',
            ],
            'reference' => '1234',
            'voided' => true,
            'deleted' => true,
            'applied_to' => [
                [
                    'amount' => 400,
                    'invoice' => [
                        'number' => 'INV-00001',
                    ],
                    'credit_note' => [
                        'number' => 'CN-00001',
                    ],
                    'document_type' => 'invoice',
                ],
            ],
        ];

        $expected = new AccountingPayment(
            integration: IntegrationType::NetSuite,
            accountingId: '789',
            values: [
                'reference' => '1234',
            ],
            currency: 'usd',
            customer: new AccountingCustomer(
                integration: IntegrationType::NetSuite,
                accountingId: '',
                values: ['name' => 'My Customer'],
            ),
            appliedTo: [
                new AccountingPaymentItem(
                    amount: new Money('usd', 40000),
                    invoice: new AccountingInvoice(
                        integration: IntegrationType::NetSuite,
                        accountingId: '',
                        values: ['number' => 'INV-00001'],
                    ),
                    creditNote: new AccountingCreditNote(
                        integration: IntegrationType::NetSuite,
                        accountingId: '',
                        values: ['number' => 'CN-00001'],
                    ),
                    documentType: 'invoice',
                ),
            ],
            voided: true,
            deleted: true,
        );

        $accountingDocument = TransformerHelper::makePayment(IntegrationType::NetSuite, $input, new Company(['currency' => 'usd']));
        $this->assertEquals($expected, $accountingDocument);
    }

    public function testGetModelValue(): void
    {
        $input = new class() extends AccountingWritableModel {
            public string $name = 'test';
            public array $taxes = [
                'tax1',
                'tax2',
                'tax3',
            ];
            public array $invoices;
            public Invoice $invoice;
            public object $class;

            public function __construct()
            {
                parent::__construct();

                $invoice1 = new Invoice();
                $invoice1->number = 'INV-0001';
                $invoice2 = new Invoice();
                $invoice2->number = 'INV-0002';
                $invoice2->subscription = new Subscription([
                    'name' => 'test',
                ]);

                $this->invoices = [$invoice1, $invoice2];
                $this->invoice = $invoice2;
                $this->class = (object) [
                    'name' => 'test',
                ];
            }

            public function getAccountingObjectReference(): InvoicedObjectReference
            {
                return new InvoicedObjectReference('test', 'test');
            }
        };

        $this->assertEquals('test', TransformerHelper::getModelValue($input, 'name'));
        $this->assertNull(TransformerHelper::getModelValue($input, 'test'));
        $this->assertEquals($input->taxes, TransformerHelper::getModelValue($input, 'taxes[]'));
        $this->assertEquals([
            'INV-0001',
            'INV-0002',
        ], TransformerHelper::getModelValue($input, 'invoices[]/number'));
        $this->assertEquals([
            null,
            null,
        ], TransformerHelper::getModelValue($input, 'invoices[]'));
        $this->assertEquals([
            null,
            null,
        ], TransformerHelper::getModelValue($input, 'invoices[]/number/fail'));
        $this->assertEquals([
            null,
            'test',
        ], TransformerHelper::getModelValue($input, 'invoices[]/subscription/name'));
        $this->assertEquals([
            null,
            null,
        ], TransformerHelper::getModelValue($input, 'invoices[]/subscription/fail'));
        $this->assertEquals('INV-0002', TransformerHelper::getModelValue($input, 'invoice/number'));
        // id is not set, so id() return false
        $this->assertEquals(false, TransformerHelper::getModelValue($input, 'invoice'));
        $this->assertNull(TransformerHelper::getModelValue($input, 'invoice/number/fail'));
        $this->assertEquals('test', TransformerHelper::getModelValue($input, 'invoice/subscription/name'));
        $this->assertNull(TransformerHelper::getModelValue($input, 'invoice/subscription/fail'));
        $this->assertEquals('test', TransformerHelper::getModelValue($input, 'class/name'));
        $this->assertEquals((object) ['name' => 'test'], TransformerHelper::getModelValue($input, 'class'));
        $this->assertNull(TransformerHelper::getModelValue($input, 'class/name/fail'));
    }

    public function testGetModelValue2(): void
    {
        $input = new Invoice();
        $input->name = 'test';
        $input->taxes = ['tax1', 'tax2', 'tax3'];
        $input->items = [
            new LineItem([
                'name' => 'item1',
                'quantity' => 1,
                'unit_cost' => 100,
            ]),
            new LineItem([
                'name' => 'item2',
                'quantity' => 2,
                'unit_cost' => 200,
            ]),
        ];
        $input->subscription = new Subscription([
            'name' => 'test',
        ]);

        $this->assertEquals('test', TransformerHelper::getModelValue($input, 'name'));
        $this->assertNull(TransformerHelper::getModelValue($input, 'test'));
        $this->assertEquals($input->taxes, TransformerHelper::getModelValue($input, 'taxes[]'));
        $this->assertEquals([
            100,
            200,
        ], TransformerHelper::getModelValue($input, 'items[]/unit_cost'));
        $this->assertEquals([
            null,
            null,
        ], TransformerHelper::getModelValue($input, 'items[]'));
        $this->assertEquals([
            null,
            null,
        ], TransformerHelper::getModelValue($input, 'items[]/number/fail'));
        $this->assertEquals('test', TransformerHelper::getModelValue($input, 'subscription/name'));
        $this->assertNull(TransformerHelper::getModelValue($input, 'subscription/fail'));
        // id is not set, so id() return false
        $this->assertEquals(false, TransformerHelper::getModelValue($input, 'invoice'));
        $this->assertNull(TransformerHelper::getModelValue($input, 'invoice/number/fail'));
        $this->assertNull(TransformerHelper::getModelValue($input, 'invoice/subscription/fail'));
    }

    public function testGetJsonValue(): void
    {
        $input = (object) ['test' => true, 'nested' => (object) ['property' => true]];
        $this->assertTrue(TransformerHelper::getJsonValue($input, 'test'));
        $this->assertNull(TransformerHelper::getJsonValue($input, 'test2'));
        $this->assertEquals((object) ['property' => true], TransformerHelper::getJsonValue($input, 'nested'));
        $this->assertTrue(TransformerHelper::getJsonValue($input, 'nested/property'));
        $this->assertNull(TransformerHelper::getJsonValue($input, 'nested/fail'));
        $this->assertNull(TransformerHelper::getJsonValue($input, 'nested/property/fail'));
    }

    public function testGetXmlValue(): void
    {
        $xml = '<result><test>true</test><nested><property>true</property></nested><empty></empty></result>';
        /** @var SimpleXMLElement $input */
        $input = simplexml_load_string($xml);
        $this->assertEquals('true', TransformerHelper::getXmlValue($input, 'test', false));
        $this->assertNull(TransformerHelper::getXmlValue($input, 'test2', false));
        $this->assertEquals(simplexml_load_string('<nested><property>true</property></nested>'), TransformerHelper::getXmlValue($input, 'nested', false));
        $this->assertEquals('true', TransformerHelper::getXmlValue($input, 'nested/property', false));
        $this->assertNull(TransformerHelper::getXmlValue($input, 'nested/fail', false));
        $this->assertNull(TransformerHelper::getXmlValue($input, 'nested/property/fail', false));
        $this->assertEquals('', TransformerHelper::getXmlValue($input, 'empty', false));
        $this->assertNull(TransformerHelper::getXmlValue($input, 'empty', true));
    }

    public function testSetValue(): void
    {
        $output = [];
        TransformerHelper::setValue($output, 'test', true);
        $this->assertEquals(['test' => true], $output);

        TransformerHelper::setValue($output, 'nested/test', true);
        $this->assertEquals(['test' => true, 'nested' => ['test' => true]], $output);

        TransformerHelper::setValue($output, 'nested/test2', true);
        $this->assertEquals(['test' => true, 'nested' => ['test' => true, 'test2' => true]], $output);

        TransformerHelper::setValue($output, 'items[]/name', ['item1', 'item2', 'item3']);
        $this->assertEquals([
            'test' => true,
            'nested' => [
                'test' => true,
                'test2' => true,
            ],
            'items' => [
                [
                    'name' => 'item1',
                ],
                [
                    'name' => 'item2',
                ],
                [
                    'name' => 'item3',
                ],
            ],
        ], $output);

        TransformerHelper::setValue($output, 'items[]/price', [100, 200, 300]);
        $this->assertEquals([
            'test' => true,
            'nested' => [
                'test' => true,
                'test2' => true,
            ],
            'items' => [
                [
                    'name' => 'item1',
                    'price' => 100,
                ],
                [
                    'name' => 'item2',
                    'price' => 200,
                ],
                [
                    'name' => 'item3',
                    'price' => 300,
                ],
            ],
        ], $output);
    }
}
