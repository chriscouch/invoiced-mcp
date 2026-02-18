<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Models\Item;
use App\Imports\Importers\Spreadsheet\ItemImporter;
use App\Imports\Models\Import;
use App\Integrations\AccountingSync\Models\AccountingItemMapping;
use App\Integrations\Enums\IntegrationType;
use Mockery;

class ItemImporterTest extends ImporterTestBase
{
    protected function getImporter(): ItemImporter
    {
        return self::getService('test.importer_factory')->get('item');
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
        $this->assertEquals(1, $result->getNumCreated());
        $this->assertEquals(0, $result->getNumUpdated());

        // should create a catalog item
        /** @var Item $catalogItem */
        $catalogItem = Item::getCurrent('test-item');
        $this->assertInstanceOf(Item::class, $catalogItem);

        $expected = [
            'id' => 'test-item',
            'object' => 'item',
            'name' => 'Test Item',
            'type' => 'service',
            'description' => "This\nIs\nA\nTest",
            'gl_account' => null,
            'currency' => 'usd',
            'unit_cost' => 5000,
            'discountable' => true,
            'taxable' => true,
            'avalara_tax_code' => null,
            'avalara_location_code' => null,
            'taxes' => [],
            'metadata' => (object) ['test' => '1234'],
        ];

        $arr = $catalogItem->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertEquals(self::$company->id(), $catalogItem->tenant_id);

        // should update the position
        $this->assertEquals(1, $import->position);

        // should create an accounting mapping
        $mapping = AccountingItemMapping::find($catalogItem->id());
        $this->assertInstanceOf(AccountingItemMapping::class, $mapping);
        $this->assertEquals(IntegrationType::Intacct, $mapping->getIntegrationType());
        $this->assertEquals('4321', $mapping->accounting_id);
        $this->assertEquals('accounting_system', $mapping->source);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines2();
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];
        $lines[0][4] = '$6,000';

        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumCreated());
        $this->assertEquals(1, $result->getNumUpdated());

        // should create a catalog item
        /** @var Item $catalogItem */
        $catalogItem = Item::getCurrent('test-item');
        $this->assertInstanceOf(Item::class, $catalogItem);

        $expected = [
            'id' => 'test-item',
            'object' => 'item',
            'name' => 'Test Item',
            'type' => 'service',
            'description' => "This\nIs\nA\nTest",
            'gl_account' => null,
            'currency' => 'usd',
            'unit_cost' => 6000,
            'discountable' => true,
            'taxable' => true,
            'taxes' => [],
            'avalara_tax_code' => null,
            'avalara_location_code' => null,
            'metadata' => (object) ['test' => '1234'],
        ];

        $arr = $catalogItem->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertEquals(self::$company->id(), $catalogItem->tenant_id);

        // should update the position
        $this->assertEquals(1, $import->position);

        $this->assertNull(AccountingItemMapping::find($catalogItem->id()));
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $item = new Item();
        $item->name = 'Delete Test';
        $item->saveOrFail();

        $mapping = ['id'];
        $lines = [
            [
                $item->id,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(Item::getCurrent($item->id));
    }

    protected function getLines(): array
    {
        return [
            [
                'test-item',
                'service',
                'Test Item',
                "This\nIs\nA\nTest",
                '$5,000',
                '1234',
                'intacct',
                '4321',
            ],
        ];
    }

    protected function getLines2(): array
    {
        return [
            [
                'test-item',
                'service',
                'Test Item',
                "This\nIs\nA\nTest",
                '$5,000',
                '1234',
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'id',
            'type',
            'name',
            'description',
            'unit_cost',
            'metadata.test',
            'accounting_system',
            'accounting_id',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'item';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'id' => 'test-item',
                'type' => 'service',
                'name' => 'Test Item',
                'description' => "This\nIs\nA\nTest",
                'unit_cost' => 5000.0,
                'metadata' => (object) ['test' => '1234'],
                'accounting_system' => IntegrationType::Intacct,
                'accounting_id' => '4321',
            ],
        ];
    }
}
