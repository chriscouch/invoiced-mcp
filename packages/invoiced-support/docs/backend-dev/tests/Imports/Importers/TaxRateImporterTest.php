<?php

namespace App\Tests\Imports\Importers;

use App\Imports\Importers\Spreadsheet\TaxRateImporter;
use App\Imports\Models\Import;
use App\SalesTax\Models\TaxRate;
use Mockery;

class TaxRateImporterTest extends ImporterTestBase
{
    protected function getImporter(): TaxRateImporter
    {
        return self::getService('test.importer_factory')->get('tax_rate');
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

        // should create a tax rate
        /** @var TaxRate $taxRate */
        $taxRate = TaxRate::getCurrent('test-rate');
        $this->assertInstanceOf(TaxRate::class, $taxRate);

        $expected = [
            'id' => 'test-rate',
            'object' => 'tax_rate',
            'name' => 'Test Rate',
            'currency' => null,
            'is_percent' => true,
            'value' => 10,
            'inclusive' => false,
            'metadata' => (object) ['test' => '1234'],
        ];

        $arr = $taxRate->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertEquals(self::$company->id(), $taxRate->tenant_id);

        // should update the position
        $this->assertEquals(1, $import->position);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];
        $lines[0][3] = '12';

        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumCreated());
        $this->assertEquals(1, $result->getNumUpdated());

        // should create a tax rate
        /** @var TaxRate $taxRate */
        $taxRate = TaxRate::getCurrent('test-rate');
        $this->assertInstanceOf(TaxRate::class, $taxRate);

        $expected = [
            'id' => 'test-rate',
            'object' => 'tax_rate',
            'name' => 'Test Rate',
            'currency' => null,
            'is_percent' => true,
            'value' => 12,
            'inclusive' => false,
            'metadata' => (object) ['test' => '1234'],
        ];

        $arr = $taxRate->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertEquals(self::$company->id(), $taxRate->tenant_id);

        // should update the position
        $this->assertEquals(1, $import->position);
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $taxRate = new TaxRate();
        $taxRate->name = 'Delete Test';
        $taxRate->value = 5;
        $taxRate->saveOrFail();

        $mapping = ['id'];
        $lines = [
            [
                $taxRate->id,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(TaxRate::getCurrent($taxRate->id));
    }

    protected function getLines(): array
    {
        return [
            [
                'test-rate',
                'Test Rate',
                '1',
                '10',
                '1234',
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'id',
            'name',
            'is_percent',
            'value',
            'metadata.test',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'tax_rate';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'id' => 'test-rate',
                'name' => 'Test Rate',
                'is_percent' => '1',
                'value' => 10.0,
                'metadata' => (object) ['test' => '1234'],
            ],
        ];
    }
}
