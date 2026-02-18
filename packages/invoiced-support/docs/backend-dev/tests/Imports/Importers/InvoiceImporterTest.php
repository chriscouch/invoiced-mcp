<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\Models\InvoiceChasingCadence;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Importers\Spreadsheet\InvoiceImporter;
use App\Imports\Models\Import;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;
use Mockery;
use stdClass;

class InvoiceImporterTest extends ImporterTestBase
{
    const SHIP_TO = [
        'name' => 'ship_to_name',
        'attention_to' => 'ship_to_attention_to',
        'address1' => 'ship_to_address1',
        'address2' => 'ship_to_address2',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78735',
        'country' => 'US',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company->features->enable('smart_chasing');
        self::$company->features->enable('invoice_chasing');
        self::$company->accounts_receivable_settings->auto_apply_credits = true;
        self::$company->accounts_receivable_settings->saveOrFail();

        $cadence = new InvoiceChasingCadence();
        $cadence->name = 'Test Cadence';
        $cadence->chase_schedule = [
            [
                'trigger' => InvoiceChasingCadence::ON_ISSUE,
                'options' => [
                    'email' => true,
                    'sms' => false,
                    'letter' => false,
                    'hour' => 7,
                ],
            ],
        ];
        $cadence->saveOrFail();
    }

    protected function getImporter(): InvoiceImporter
    {
        return self::getService('test.importer_factory')->get('invoice');
    }

    public function testRunCreate(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $import = $this->getImport();

        $records = $importer->build($mapping, $lines, [], $import);
        $result = $importer->run($records, [], $import);

        // verify result
        $this->assertEquals(3, $result->getNumCreated());
        $this->assertEquals(0, $result->getNumUpdated());

        // should create a customer
        $customer = Customer::where('name', 'Sherlock Holmes')->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('sherlock@example.com', $customer->email);
        $this->assertEquals('701 Brazos St', $customer->address1);
        $this->assertEquals('Suite 1616', $customer->address2);
        $this->assertEquals('Austin', $customer->city);
        $this->assertEquals('TX', $customer->state);
        $this->assertEquals('78701', $customer->postal_code);
        $this->assertEquals('US', $customer->country);

        // should create an invoice
        $invoice = Invoice::where('number', 'INV-TEST')->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($customer->id(), $invoice->customer);

        // should create an accounting mapping
        $mapping = AccountingInvoiceMapping::find($invoice->id);
        $this->assertInstanceOf(AccountingInvoiceMapping::class, $mapping);
        $this->assertEquals(IntegrationType::Intacct, $mapping->getIntegrationType());
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertEquals('accounting_system', $mapping->source);

        // should create another invoice
        $invoice2 = Invoice::where('number', 'INV-00001')->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice2);
        $this->assertEquals($customer->id(), $invoice2->customer);

        $shipToResult = $invoice2->ship_to->toArray(); /* @phpstan-ignore-line */
        unset($shipToResult['created_at']);
        unset($shipToResult['updated_at']);
        $this->assertEquals(self::SHIP_TO, $shipToResult);
        $expected = [
            'customer' => $customer->id(),
            'name' => 'Invoice',
            'currency' => 'usd',
            'number' => 'INV-TEST',
            'payment_terms' => null,
            'purchase_order' => null,
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1.0,
                    'name' => 'test item',
                    'unit_cost' => 1000.0,
                    'amount' => 1000.0,
                    'type' => 'product',
                    'description' => null,
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => (object) ['test2' => '4567'],
                ],
                [
                    'catalog_item' => null,
                    'quantity' => 5.0,
                    'name' => '',
                    'unit_cost' => 5.0,
                    'amount' => 25.0,
                    'type' => null,
                    'description' => 'description',
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'notes' => null,
            'subtotal' => 1025.0,
            'discounts' => [
                [
                    'coupon' => null,
                    'amount' => 15.0,
                    'expires' => null,
                    'from_payment_terms' => false,
                ],
            ],
            'taxes' => [
                [
                    'tax_rate' => null,
                    'amount' => 7.2,
                ],
            ],
            'shipping' => [],
            'total' => 1017.20,
            'payment_plan' => null,
            'draft' => false,
            'closed' => false,
            'paid' => false,
            'balance' => 1017.20,
            'status' => InvoiceStatus::PastDue->value,
            'next_chase_on' => null,
            'needs_attention' => false,
            'autopay' => false,
            'payment_source' => null,
            'attempt_count' => 0,
            'next_payment_attempt' => null,
            'metadata' => (object) ['test' => '1234'],
            'ship_to' => self::SHIP_TO,
            'late_fees' => true,
            'network_document_id' => null,
            'subscription_id' => null,
        ];
        $arr = $invoice->toArray();

        foreach (['object', 'created_at', 'updated_at', 'id', 'date', 'due_date', 'chase', 'last_sent', 'url', 'payment_url', 'pdf_url', 'csv_url'] as $property) {
            unset($arr[$property]);
        }

        foreach ($arr['items'] as &$item) {
            unset($item['id']);
            unset($item['created_at']);
            unset($item['updated_at']);
            unset($item['object']);
        }
        foreach ($arr['discounts'] as &$discount) {
            unset($discount['id']);
            unset($discount['object']);
            unset($discount['updated_at']);
        }
        foreach ($arr['taxes'] as &$tax) {
            unset($tax['id']);
            unset($tax['object']);
            unset($tax['updated_at']);
        }
        unset($arr['ship_to']['created_at']);
        unset($arr['ship_to']['updated_at']);

        $this->assertEquals($expected, $arr);
        $this->assertEquals(self::$company->id(), $invoice->tenant_id);
        $this->assertEquals('Jul-01-2014', date('M-d-Y', $invoice->date));
        $this->assertEquals('Jul-14-2014', date('M-d-Y', $invoice->due_date));

        // should create another invoice
        $invoice3 = Invoice::where('number', 'INV-00002')->oneOrNull();
        $this->assertInstanceOf(Invoice::class, $invoice3);
        $this->assertNotEquals($customer->id(), $invoice3->customer);
        $this->assertEquals(1400, $invoice3->total);

        // should update the position
        $this->assertEquals(4, $import->position);
    }

    public function testRunVoid(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Void Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->saveOrFail();

        $mapping = ['number'];
        $lines = [
            [
                $invoice->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'void'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertTrue($invoice->refresh()->voided);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Delete Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $mapping = ['number', 'notes'];
        $lines = [
            [
                $invoice->number,
                'test',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $this->assertCount(1, $records);
        $this->assertFalse(isset($records[0]['items']));
        $importer->run($records, $options, $import);

        // verify result
        $invoice->refresh();
        $this->assertEquals('test', $invoice->notes);
        $this->assertCount(1, $invoice->items); // the items should not be removed
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Delete Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->saveOrFail();

        $mapping = ['number'];
        $lines = [
            [
                $invoice->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(Invoice::where('number', $invoice->number)->oneOrNull());
    }

    protected function getLines(): array
    {
        return [
            [
                'Sherlock Holmes',
                '',
                'sherlock@example.com',
                '701 Brazos St',
                'Suite 1616',
                'Austin',
                'Texas',
                '78701',
                '',
                'INV-TEST',
                'Jul-01-2014',
                'Jul-14-2014',
                'USD',
                // items
                '1',
                'product',
                'test item',
                '',
                '$1,000',
                // rates
                5,
                7.20,
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
                '1234',
                '4567',
                'intacct',
                '1234',
            ],
            [
                'Sherlock Holmes',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'INV-TEST',
                'Jul-01-2014',
                'Jul-14-2014',
                'USD',
                // items
                '5',
                '',
                '',
                'description',
                5,
                // rates
                10,
                0,
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
            ],
            [
                '',
                'CUST-00001',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                // items
                '2',
                '',
                'test',
                'description',
                50,
                // rates
                10,
                0,
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
                '',
                '890',
            ],
            [
                'Sherlock Holmes',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Jul-14-2014',
                'Jul-18-2014',
                'USD',
                // items
                '5',
                '',
                '',
                'description',
                '-1.23',
                // rates
                0,
                0,
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
            ],
            [
                'My Customer',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                // items
                '1',
                '',
                'Item 1',
                '',
                '100.00',
                // rates
                0,
                0,
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
            ],
            [
                'My Customer',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                // items
                '2',
                '',
                'Item 2',
                '',
                '200.00',
                // rates
                0,
                0,
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
            ],
            [
                'My Customer',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                // items
                '3',
                '',
                'Item 3',
                '',
                '300.00',
                // rates
                0,
                0,
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'customer',
            'account_number',
            'email',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'country',
            'number',
            'date',
            'due_date',
            'currency',
            // items
            'quantity',
            'type',
            'item',
            'description',
            'unit_cost',
            // rates
            'discount',
            'tax',
            'ship_to.name',
            'ship_to.attention_to',
            'ship_to.address1',
            'ship_to.address2',
            'ship_to.city',
            'ship_to.state',
            'ship_to.postal_code',
            'ship_to.country',
            'metadata.test',
            'line_item_metadata.test2',
            'accounting_system',
            'accounting_id',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save,tenant]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->shouldReceive('tenant')
            ->andReturn(self::$company);
        $import->type = 'invoice';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock Holmes',
                    'number' => '',
                    'email' => 'sherlock@example.com',
                    'address1' => '701 Brazos St',
                    'address2' => 'Suite 1616',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '78701',
                    'country' => '',
                ],
                'number' => 'INV-TEST',
                'date' => mktime(6, 0, 0, 7, 1, 2014),
                'due_date' => mktime(18, 0, 0, 7, 14, 2014),
                'currency' => 'USD',
                'tax' => 7.2,
                'discount' => 15.0,
                'items' => [
                    [
                        'name' => 'test item',
                        'description' => '',
                        'unit_cost' => 1000.0,
                        'quantity' => 1.0,
                        'type' => 'product',
                        'metadata' => (object) ['test2' => '4567'],
                    ],
                    [
                        'quantity' => 5.0,
                        'description' => 'description',
                        'unit_cost' => 5.0,
                        'type' => '',
                        'name' => '',
                    ],
                ],
                'metadata' => (object) ['test' => '1234'],
                'ship_to' => self::SHIP_TO,
                'accounting_system' => IntegrationType::Intacct,
                'accounting_id' => '1234',
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'number' => 'CUST-00001',
                    'name' => '',
                    'email' => '',
                    'address1' => '',
                    'address2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'country' => '',
                ],
                'number' => '',
                'date' => '',
                'due_date' => '',
                'currency' => '',
                'tax' => 0.0,
                'discount' => 10.0,
                'items' => [
                    [
                        'name' => 'test',
                        'quantity' => 2.0,
                        'description' => 'description',
                        'unit_cost' => 50.0,
                        'type' => '',
                        'metadata' => (object) ['test2' => '890'],
                    ],
                ],
                'ship_to' => self::SHIP_TO,
                'metadata' => (object) ['test' => null],
                'accounting_system' => null,
                'accounting_id' => null,
            ],
            // NOTE: this should not be imported because it is negative
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock Holmes',
                    'number' => '',
                    'email' => '',
                    'address1' => '',
                    'address2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'country' => '',
                ],
                'number' => '',
                'date' => mktime(6, 0, 0, 7, 14, 2014),
                'due_date' => mktime(18, 0, 0, 7, 18, 2014),
                'currency' => 'USD',
                'items' => [
                    [
                        'description' => 'description',
                        'quantity' => 5.0,
                        'unit_cost' => -1.23,
                        'type' => '',
                        'name' => '',
                    ],
                ],
                'discount' => 0.0,
                'tax' => 0.0,
                'ship_to' => self::SHIP_TO,
                'metadata' => (object) ['test' => null],
                'accounting_system' => null,
                'accounting_id' => null,
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'My Customer',
                    'number' => '',
                    'email' => '',
                    'address1' => '',
                    'address2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'country' => '',
                ],
                'number' => '',
                'date' => '',
                'due_date' => '',
                'currency' => '',
                'items' => [
                    [
                        'name' => 'Item 1',
                        'quantity' => 1.0,
                        'unit_cost' => 100.00,
                        'type' => '',
                        'description' => '',
                    ],
                    [
                        'name' => 'Item 2',
                        'quantity' => 2.0,
                        'unit_cost' => 200.00,
                        'type' => '',
                        'description' => '',
                    ],
                    [
                        'name' => 'Item 3',
                        'quantity' => 3.0,
                        'unit_cost' => 300.00,
                        'type' => '',
                        'description' => '',
                    ],
                ],
                'discount' => 0.0,
                'tax' => 0.0,
                'ship_to' => self::SHIP_TO,
                'metadata' => (object) ['test' => null],
                'accounting_system' => null,
                'accounting_id' => null,
            ],
        ];
    }

    /**
     * Tests that an empty ship_to array is removed from the
     * record.
     */
    public function testINVD2593(): void
    {
        $importer = $this->getImporter();
        $mapping = [
            'customer',
            'item',
            'unit_cost',
            'ship_to.name',
            'ship_to.attention_to',
            'ship_to.address1',
            'ship_to.address2',
            'ship_to.city',
            'ship_to.state',
            'ship_to.postal_code',
            'ship_to.country',
        ];

        $lines = [
            [
                'INVD-2593 Customer',
                'Test Item',
                '1000',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        ];

        $import = $this->getImport();
        $records = $importer->build($mapping, $lines, [], $import);

        $this->assertEquals(1, count($records));
        $record = $records[0];
        $this->assertFalse(isset($record['ship_to']));
    }

    public function testBuildRecordBooleans(): void
    {
        $importer = $this->getImporter();
        $mapping = [
            'customer',
            'autopay',
            'draft',
            'sent',
            'closed',
            'late_fees',
        ];

        $lines = [
            [
                'INVD-2593 Customer',
                false,
                false,
                false,
                false,
                false,
            ],
        ];

        $import = $this->getImport();

        $importEdgeCases = [-1, 2, 10, 'ye', 'n', 'null'];
        foreach ($importEdgeCases as $case) {
            $lines[0][1] = $case;
            try {
                $importer->build($mapping, $lines, [], $import);
                $this->assertFalse(true, "Exception not caught ($case)");
            } catch (ValidationException $e) {
            }
        }

        $lines[0] = [
                'INVD-2593 Customer',
                1,
                true,
                'TRUE',
                'true',
                'oN',
            ];
        $records = $importer->build($mapping, $lines, [], $import);
        $this->assertEquals([
            'customer' => ['name' => 'INVD-2593 Customer'],
            'autopay' => true,
            'draft' => true,
            'sent' => true,
            'closed' => true,
            'late_fees' => true,
        ], array_intersect_key($records[0], array_flip($mapping)));

        $lines[0] = [
                'INVD-2593 Customer',
                0,
                false,
                'oFf',
                'FALSE',
                '',
            ];
        $records = $importer->build($mapping, $lines, [], $import);
        $this->assertEquals([
            'customer' => ['name' => 'INVD-2593 Customer'],
            'autopay' => false,
            'draft' => false,
            'sent' => false,
            'closed' => false,
            'late_fees' => false,
        ], array_intersect_key($records[0], array_flip($mapping)));
    }

    /**
     * Tests that 'items' is only set on the record when line item data is provided.
     * Ticket: INVD-2830.
     */
    public function testNoLineItems(): void
    {
        $mapping = [
            'customer',
            'number',
        ];
        // NOTE:
        // Two of the same invoices are used to test that no line item merging occurs.
        $lines = [
            [
                'INVD-2830 Customer',
                'INVD-2830-1',
            ],
            [
                'INVD-2830 Customer',
                'INVD-2830-1',
            ],
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, [], $import);
        $this->assertCount(1, $records);
        $this->assertFalse(isset($records[0]['items']));
    }

    /**
     * Tests line item merging functionality.
     */
    public function testLineItemsMerge(): void
    {
        // 1st case: both records have line item data
        $mapping = [
            'customer',
            'number',
            'description',
        ];
        $lines = [
            [
                'INVD-2830 Customer',
                'INVD-2830-1',
                'First Line Item',
            ],
            [
                'INVD-2830 Customer',
                'INVD-2830-1',
                'Second Line Item',
            ],
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, [], $import);
        $this->assertCount(1, $records);
        $this->assertTrue(isset($records[0]['items']));
        $this->assertCount(2, $records[0]['items']);
        $this->assertEquals('First Line Item', $records[0]['items'][0]['description']);
        $this->assertEquals('Second Line Item', $records[0]['items'][1]['description']);

        // 2nd case: only one record has line item data
        $lines = [
            [
                'INVD-2830 Customer',
                'INVD-2830-1',
                'First Line Item',
            ],
            [
                'INVD-2830 Customer',
                'INVD-2830-1',
            ],
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, [], $import);
        $this->assertCount(1, $records);
        $this->assertTrue(isset($records[0]['items']));
        $this->assertCount(1, $records[0]['items']);
        $this->assertEquals('First Line Item', $records[0]['items'][0]['description']);
    }

    public function testInvoiceDelivery(): void
    {
        // create
        $mapping = [
            'number',
            'customer',
            'chasing_cadence',
            'description',
            'unit_cost',
            'quantity',
        ];
        $lines = [
            [
                'INV-CHASE-0001',
                'Chase Customer',
                'Test Cadence',
                'Line Item',
                100,
                1,
            ],
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, [], $import);
        $importer->run($records, [], $import);

        /** @var Invoice $invoice */
        $invoice = Invoice::where('number', 'INV-CHASE-0001')
            ->oneOrNull();
        $this->assertNotNull($invoice);

        /** @var InvoiceDelivery $delivery */
        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id())
            ->oneOrNull();
        $this->assertNotNull($delivery);

        // create with negative balance
        $lines = [
            [
                'INV-CHASE-0002',
                'Chase Customer',
                'Test Cadence',
                'Line Item',
                100,
                1,
            ],
        ];
        /** @var Customer $customer */
        $customer = Customer::where('name', 'Chase Customer')->one();
        self::$customer = $customer;
        self::hasCredit();
        self::hasCredit();

        $records = $importer->build($mapping, $lines, [], $import);
        $importer->run($records, [], $import);

        $invoice2 = Invoice::where('number', 'INV-CHASE-0002')
            ->oneOrNull();
        $this->assertNotNull($invoice2);
        $delivery2 = InvoiceDelivery::where('invoice_id', $invoice2->id())
            ->oneOrNull();
        $this->assertNull($delivery2);

        // update
        $mapping = [
            'number',
            'customer',
            'chasing_disabled',
        ];
        $lines = [
            [
                'INV-CHASE-0001',
                'Chase Customer',
                true,
            ],
        ];

        $options = [
            'operation' => 'update',
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);
        $this->assertTrue($delivery->refresh()->disabled);

        // chasing should be re-enabled
        $lines = [
            [
                'INV-CHASE-0001',
                'Chase Customer',
                false,
            ],
        ];

        $options = [
            'operation' => 'update',
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);
        $this->assertFalse($delivery->refresh()->disabled);
    }

    public function testRates(): void
    {
        // test that taxes are not included
        // on the record if not provided
        $mapping = [
            'number',
            'tax',
            'discount',
        ];
        $lines = [
            [
                'INV-TAX-0001',
                null,
            ],
            [
                'INV-TAX-0001',
                null,
            ],
            [
                'INV-TAX-0001',
                '',
            ],
        ];
        $options = [
            'operation' => 'upsert',
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, $options, $import);
        $this->assertCount(1, $records);
        $this->assertFalse(isset($records[0]['tax']));
        $this->assertFalse(isset($records[0]['discount']));

        // tests that rate addition is correct when only the first or subsequent record
        // has a rate value
        $mapping = [
            'number',
            'tax',
            'discount',
        ];
        $lines = [
            [
                'INV-TAX-0001',
                null,
                10,
            ],
            [
                'INV-TAX-0001',
                2,
                null,
            ],
            [
                'INV-TAX-0001',
                null,
                2,
            ],
        ];
        $options = [
            'operation' => 'upsert',
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, $options, $import);
        $this->assertCount(1, $records);
        $this->assertTrue(isset($records[0]['tax']));
        $this->assertTrue(isset($records[0]['discount']));
        $this->assertIsFloat($records[0]['tax']);
        $this->assertIsFloat($records[0]['discount']);
        $this->assertEquals(2, $records[0]['tax']);
        $this->assertEquals(12, $records[0]['discount']);
    }

    public function testInvd3091(): void
    {
        $customer = new Customer();
        $customer->name = 'INVD-3091';
        $customer->country = 'US';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $import = $this->getImport();
        $importer = $this->getImporter();

        $mapping = ['number', 'item', 'unit_cost'];
        $lines = [[$invoice->number.'  ', 'Test', 100]];

        $options = ['operation' => 'upsert'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumFailed(), (string) json_encode($result->getFailures()));
        $this->assertEquals(0, $result->getNumCreated());
        $this->assertEquals(1, $result->getNumUpdated());
    }

    public function testEarlyPaymentDiscount(): void
    {
        $mapping = [
            'number',
            'payment_terms',
            'discount',
            'quantity',
            'unit_cost',
        ];
        $lines = [
            [
                'INV-DISC-0001',
                '10% 10 NET 10',
                10,
                1,
                100,
            ],
            [
                'INV-DISC-0001',
                null,
                null,
                1,
                50,
            ],
            [
                'INV-DISC-0001',
                null,
                2,
                1,
                50,
            ],
        ];
        $options = [
            'operation' => 'upsert',
        ];

        $import = $this->getImport();
        $importer = $this->getImporter();
        $records = $importer->build($mapping, $lines, $options, $import);
        $this->assertCount(2, $records[0]['discounts']);
        $this->assertEquals(12, $records[0]['discounts'][0]['amount']);
        $this->assertArrayNotHasKey('expires', $records[0]['discounts'][0]);
        $this->assertEquals(20, $records[0]['discounts'][1]['amount']);
        $this->assertGreaterThan(time(), $records[0]['discounts'][1]['expires']);
    }
}
