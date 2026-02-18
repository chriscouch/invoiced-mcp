<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\ValueObjects\CreditNoteStatus;
use App\Imports\Importers\Spreadsheet\CreditNoteImporter;
use App\Imports\Models\Import;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\Enums\IntegrationType;
use Mockery;

class CreditNoteImporterTest extends ImporterTestBase
{
    protected function getImporter(): CreditNoteImporter
    {
        return self::getService('test.importer_factory')->get('credit_note');
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

        // should create a credit note
        $creditNote1 = CreditNote::where('number', 'CN-TEST')->oneOrNull();
        $this->assertInstanceOf(CreditNote::class, $creditNote1);

        // should create an accounting mapping
        $mapping = AccountingCreditNoteMapping::find($creditNote1->id);
        $this->assertInstanceOf(AccountingCreditNoteMapping::class, $mapping);
        $this->assertEquals(IntegrationType::Intacct, $mapping->getIntegrationType());
        $this->assertEquals('1234', $mapping->accounting_id);
        $this->assertEquals('accounting_system', $mapping->source);

        // should create another credit note
        $creditNote2 = CreditNote::where('number', 'CN-00001')->oneOrNull();
        $this->assertInstanceOf(CreditNote::class, $creditNote2);

        $expected = [
            'name' => 'Credit Note',
            'currency' => 'usd',
            'number' => 'CN-TEST',
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
                    'metadata' => (object) ['test2' => '1001'],
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
                    'metadata' => (object) ['test2' => '1003'],
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
            'draft' => false,
            'closed' => false,
            'paid' => false,
            'balance' => 1017.20,
            'status' => CreditNoteStatus::OPEN,
            'metadata' => (object) ['test' => '1000'],
            'customer' => $customer->id,
            'invoice' => null,
            'network_document_id' => null,
        ];
        $arr = $creditNote1->toArray();

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
            unset($discount['updated_at']);
            unset($discount['object']);
        }
        foreach ($arr['taxes'] as &$tax) {
            unset($tax['id']);
            unset($tax['updated_at']);
            unset($tax['object']);
        }

        $this->assertEquals($expected, $arr);
        $this->assertEquals(self::$company->id(), $creditNote1->tenant_id);
        $this->assertEquals('Jul-01-2014', date('M-d-Y', $creditNote1->date));

        // should create another credit note
        $creditNote3 = CreditNote::where('number', 'CN-00002')->oneOrNull();
        $this->assertInstanceOf(CreditNote::class, $creditNote3);
        $this->assertEquals(1400, $creditNote3->total);

        // should update the position
        $this->assertEquals(4, $import->position);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Delete Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $creditNote = new CreditNote();
        $creditNote->setCustomer($customer);
        $creditNote->items = [['unit_cost' => 100]];
        $creditNote->saveOrFail();

        $mapping = ['number', 'notes'];
        $lines = [
            [
                $creditNote->number,
                'test',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals('test', $creditNote->refresh()->notes);
    }

    public function testRunVoid(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Void Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $creditNote = new CreditNote();
        $creditNote->setCustomer($customer);
        $creditNote->saveOrFail();

        $mapping = ['number'];
        $lines = [
            [
                $creditNote->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'void'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertTrue($creditNote->refresh()->voided);
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Delete Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $creditNote = new CreditNote();
        $creditNote->setCustomer($customer);
        $creditNote->saveOrFail();

        $mapping = ['number'];
        $lines = [
            [
                $creditNote->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(CreditNote::where('number', $creditNote->number)->oneOrNull());
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
                'TX',
                '78701',
                'US',
                'CN-TEST',
                'Jul-01-2014',
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
                '1000',
                '1001',
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
                'CN-TEST',
                'Jul-01-2014',
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
                '1002',
                '1003',
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
                // items
                '2',
                '',
                'test',
                'description',
                50,
                // rates
                10,
                0,
                '1004',
                '1005',
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
                '1006',
                '1007',
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
                // items
                '1',
                '',
                'Item 1',
                '',
                '100.00',
                // rates
                0,
                0,
                '1008',
                '1009',
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
                // items
                '2',
                '',
                'Item 2',
                '',
                '200.00',
                // rates
                0,
                0,
                '1010',
                '1011',
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
                // items
                '3',
                '',
                'Item 3',
                '',
                '300.00',
                // rates
                0,
                0,
                '1012',
                '1013',
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
        $import->type = 'credit_note';

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
                    'country' => 'US',
                ],
                'number' => 'CN-TEST',
                'date' => mktime(6, 0, 0, 7, 1, 2014),
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
                        'metadata' => (object) ['test2' => '1001'],
                    ],
                    [
                        'quantity' => 5.0,
                        'description' => 'description',
                        'unit_cost' => 5.0,
                        'type' => '',
                        'name' => '',
                        'metadata' => (object) ['test2' => '1003'],
                    ],
                ],
                'metadata' => (object) ['test' => '1000'],
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
                        'metadata' => (object) ['test2' => '1005'],
                    ],
                ],
                'metadata' => (object) ['test' => '1004'],
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
                'currency' => 'USD',
                'items' => [
                    [
                        'description' => 'description',
                        'quantity' => 5.0,
                        'unit_cost' => -1.23,
                        'type' => '',
                        'name' => '',
                        'metadata' => (object) ['test2' => '1007'],
                    ],
                ],
                'discount' => 0.0,
                'tax' => 0.0,
                'metadata' => (object) ['test' => '1006'],
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
                'currency' => '',
                'items' => [
                    [
                        'name' => 'Item 1',
                        'quantity' => 1.0,
                        'unit_cost' => 100.00,
                        'type' => '',
                        'description' => '',
                        'metadata' => (object) ['test2' => '1009'],
                    ],
                    [
                        'name' => 'Item 2',
                        'quantity' => 2.0,
                        'unit_cost' => 200.00,
                        'type' => '',
                        'description' => '',
                        'metadata' => (object) ['test2' => '1011'],
                    ],
                    [
                        'name' => 'Item 3',
                        'quantity' => 3.0,
                        'unit_cost' => 300.00,
                        'type' => '',
                        'description' => '',
                        'metadata' => (object) ['test2' => '1013'],
                    ],
                ],
                'discount' => 0.0,
                'tax' => 0.0,
                'metadata' => (object) ['test' => '1008'],
                'accounting_system' => null,
                'accounting_id' => null,
            ],
        ];
    }
}
