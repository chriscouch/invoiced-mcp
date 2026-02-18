<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Imports\Importers\Spreadsheet\EstimateImporter;
use App\Imports\Models\Import;
use Mockery;
use stdClass;

class EstimateImporterTest extends ImporterTestBase
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

    protected function getImporter(): EstimateImporter
    {
        return self::getService('test.importer_factory')->get('estimate');
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
        $this->assertEquals(2, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(0, $result->getNumUpdated());

        // should create a customer
        $customer = Customer::where('name', 'Test')->oneOrNull();
        $this->assertInstanceOf(Customer::class, $customer);

        // should create an estimate
        $estimate = Estimate::where('number', 'EST-00001')->oneOrNull();
        $this->assertInstanceOf(Estimate::class, $estimate);
        $this->assertEquals($customer->id(), $estimate->customer);

        // should create another estimate
        $estimate2 = Estimate::where('number', 'EST-00002')->oneOrNull();
        $this->assertInstanceOf(Estimate::class, $estimate2);
        $this->assertEquals($customer->id(), $estimate2->customer);

        $shipToResult = $estimate2->ship_to->toArray(); /* @phpstan-ignore-line */
        unset($shipToResult['created_at']);
        unset($shipToResult['updated_at']);
        $this->assertEquals(self::SHIP_TO, $shipToResult);

        $expected = [
            'customer' => $customer->id(),
            'name' => 'Estimate',
            'currency' => 'usd',
            'number' => 'EST-00001',
            'payment_terms' => null,
            'purchase_order' => null,
            'expiration_date' => null,
            'items' => [
                [
                    'catalog_item' => null,
                    'quantity' => 1,
                    'name' => 'test item',
                    'unit_cost' => 1000,
                    'amount' => 1000,
                    'type' => null,
                    'description' => '',
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
                [
                    'catalog_item' => null,
                    'quantity' => 2,
                    'name' => 'test item 2',
                    'unit_cost' => 0,
                    'amount' => 0,
                    'type' => null,
                    'description' => '',
                    'discountable' => true,
                    'discounts' => [],
                    'taxable' => true,
                    'taxes' => [],
                    'metadata' => new stdClass(),
                ],
            ],
            'notes' => null,
            'subtotal' => 1000.0,
            'discounts' => [
                [
                    'coupon' => null,
                    'amount' => 2.0,
                    'expires' => null,
                    'from_payment_terms' => false,
                ],
            ],
            'taxes' => [
                [
                    'tax_rate' => null,
                    'amount' => 15.0,
                ],
            ],
            'shipping' => [],
            'total' => 1013.0,
            'deposit' => 0,
            'deposit_paid' => false,
            'draft' => false,
            'closed' => false,
            'status' => EstimateStatus::NOT_SENT,
            'approved' => null,
            'approval' => null,
            'invoice' => null,
            'metadata' => (object) ['test' => '1234'],
            'ship_to' => self::SHIP_TO,
            'network_document_id' => null,
        ];

        $arr = $estimate->toArray();

        foreach (['object', 'created_at', 'updated_at', 'id', 'date', 'url', 'pdf_url'] as $property) {
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
        $this->assertEquals(self::$company->id(), $estimate->tenant_id);

        // should update the position
        $this->assertEquals(2, $import->position);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Delete Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $estimate = new Estimate();
        $estimate->setCustomer($customer);
        $estimate->items = [['unit_cost' => 100]];
        $estimate->saveOrFail();

        $mapping = ['number', 'notes'];
        $lines = [
            [
                $estimate->number,
                'test',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals('test', $estimate->refresh()->notes);
    }

    public function testRunVoid(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Void Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $estimate = new Estimate();
        $estimate->setCustomer($customer);
        $estimate->saveOrFail();

        $mapping = ['number'];
        $lines = [
            [
                $estimate->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'void'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertTrue($estimate->refresh()->voided);
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $customer = new Customer();
        $customer->name = 'Delete Test';
        $customer->country = 'US';
        $customer->saveOrFail();
        $estimate = new Estimate();
        $estimate->setCustomer($customer);
        $estimate->saveOrFail();

        $mapping = ['number'];
        $lines = [
            [
                $estimate->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(Estimate::where('number', $estimate->number)->oneOrNull());
    }

    protected function getLines(): array
    {
        return [
            [
                'Test',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'EST-00001',
                'Jun-20-2014',
                'USD',
                // items
                '1',
                'test item',
                '$1,000',
                // rates
                '',
                5,
                'ship_to_name',
                'ship_to_attention_to',
                'ship_to_address1',
                'ship_to_address2',
                'Austin',
                'TX',
                '78735',
                'US',
                '1234',
            ],
            [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'EST-00001',
                '',
                '',
                // items
                '2',
                'test item 2',
                '0',
                // rates
                '2',
                '10',
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
                // items
                '1',
                'test item 3',
                '0',
                // rates
                '2',
                '10',
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
            'currency',
            'quantity',
            'item',
            'unit_cost',
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
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'estimate';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Test',
                    'number' => '',
                    'email' => '',
                    'address1' => '',
                    'address2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'country' => '',
                ],
                'number' => 'EST-00001',
                'date' => mktime(6, 0, 0, 6, 20, 2014),
                'currency' => 'USD',
                'tax' => 15.0,
                'discount' => 2.0,
                'items' => [
                    [
                        'name' => 'test item',
                        'unit_cost' => 1000.0,
                        'quantity' => 1.0,
                    ],
                    [
                        'name' => 'test item 2',
                        'quantity' => 2.0,
                        'unit_cost' => 0.0,
                    ],
                ],
                'metadata' => (object) ['test' => '1234'],
                'ship_to' => self::SHIP_TO,
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
                'tax' => 10.0,
                'discount' => 2.0,
                'items' => [
                    [
                        'name' => 'test item 3',
                        'quantity' => 1.0,
                        'unit_cost' => 0.0,
                    ],
                ],
                'metadata' => (object) ['test' => null],
                'ship_to' => self::SHIP_TO,
            ],
        ];
    }
}
