<?php

namespace App\Tests\Imports\Importers;

use App\Imports\Importers\Spreadsheet\PendingLineItemImporter;
use App\Imports\Models\Import;
use App\SubscriptionBilling\Models\PendingLineItem;
use Mockery;

class PendingLineItemImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();
    }

    protected function getImporter(): PendingLineItemImporter
    {
        return self::getService('test.importer_factory')->get('pending_line_item');
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
        $this->assertEquals(7, $result->getNumCreated());
        $this->assertEquals(0, $result->getNumUpdated());

        // should create a pending line
        $lineItem = PendingLineItem::where('customer_id', self::$customer->id())->oneOrNull();
        $expected = [
            'customer' => self::$customer->id(),
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
            'metadata' => (object) ['test' => '1234'],
        ];
        $this->assertInstanceOf(PendingLineItem::class, $lineItem);
        $array = $lineItem->toArray();
        unset($array['id']);
        unset($array['object']);
        unset($array['created_at']);
        unset($array['updated_at']);
        $this->assertEquals($expected, $array);

        $this->assertEquals(7, PendingLineItem::where('customer_id', self::$customer->id())->count());

        // should update the position
        $this->assertEquals(7, $import->position);
    }

    protected function getLines(): array
    {
        return [
            [
                'Sherlock',
                '',
                // items
                '1',
                'product',
                'test item',
                '',
                '$1,000',
                // rates
                5,
                7.20,
                '1234',
            ],
            [
                'Sherlock',
                '',
                // items
                '5',
                '',
                '',
                'description',
                5,
                // rates
                10,
                0,
            ],
            [
                '',
                'CUST-00001',
                // items
                '2',
                '',
                'test',
                'description',
                50,
                // rates
                10,
                0,
                '890',
            ],
            [
                'Sherlock',
                '',
                // items
                '5',
                '',
                '',
                'description',
                '-1.23',
                // rates
                0,
                0,
            ],
            [
                'Sherlock',
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
            ],
            [
                'Sherlock',
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
            ],
            [
                'Sherlock',
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
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'customer',
            'account_number',
            // items
            'quantity',
            'type',
            'name',
            'description',
            'unit_cost',
            // rates
            'discount',
            'tax',
            'metadata.test',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save,tenant]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->shouldReceive('tenant')
            ->andReturn(self::$company);
        $import->type = 'pending_line_item';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'test item',
                'unit_cost' => 1000.0,
                'quantity' => 1.0,
                'type' => 'product',
                'description' => '',
                'discount' => 5.0,
                'tax' => 7.2,
                'metadata' => (object) ['test' => '1234'],
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => '',
                'quantity' => 5.0,
                'type' => '',
                'description' => 'description',
                'unit_cost' => 5.0,
                'discount' => 10.0,
                'tax' => 0.0,
                'metadata' => (object) ['test' => null],
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => '',
                    'number' => 'CUST-00001',
                ],
                'name' => 'test',
                'quantity' => 2.0,
                'type' => '',
                'description' => 'description',
                'unit_cost' => 50.0,
                'discount' => 10.0,
                'tax' => 0.0,
                'metadata' => (object) ['test' => '890'],
            ],
            // NOTE: this should not be imported because it is negative
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => '',
                'type' => '',
                'description' => 'description',
                'quantity' => 5.0,
                'unit_cost' => -1.23,
                'discount' => 0.0,
                'tax' => 0.0,
                'metadata' => (object) ['test' => null],
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'Item 1',
                'quantity' => 1.0,
                'unit_cost' => 100.00,
                'type' => '',
                'description' => '',
                'discount' => 0.0,
                'tax' => 0.0,
                'metadata' => (object) ['test' => null],
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'Item 2',
                'quantity' => 2.0,
                'unit_cost' => 200.00,
                'type' => '',
                'description' => '',
                'discount' => 0.0,
                'tax' => 0.0,
                'metadata' => (object) ['test' => null],
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                    'number' => '',
                ],
                'name' => 'Item 3',
                'quantity' => 3.0,
                'unit_cost' => 300.00,
                'type' => '',
                'description' => '',
                'discount' => 0.0,
                'tax' => 0.0,
                'metadata' => (object) ['test' => null],
            ],
        ];
    }
}
