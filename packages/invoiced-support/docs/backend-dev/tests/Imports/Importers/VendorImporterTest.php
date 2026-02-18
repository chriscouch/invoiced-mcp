<?php

namespace App\Tests\Imports\Importers;

use App\AccountsPayable\Models\Vendor;
use App\Imports\Importers\Spreadsheet\VendorImporter;
use App\Imports\Models\Import;
use Mockery;

class VendorImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$vendor = new Vendor();
        self::$vendor->name = 'Old';
        self::$vendor->number = 'VEND-0003';
        self::$vendor->saveOrFail();
    }

    protected function getImporter(): VendorImporter
    {
        return self::getService('test.importer_factory')->get('vendor');
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
        $this->assertEquals(0, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should create a vendor named "Test"
        /** @var Vendor $vendor */
        $vendor = Vendor::where('name', 'Test')->one();
        $this->assertInstanceOf(Vendor::class, $vendor);
        $this->assertEquals('TX', $vendor->state);
        $this->assertEquals(false, $vendor->active);

        // should create a vendor numbered "VEND-0002"
        $vendor2 = Vendor::where('number', 'VEND-0002')->oneOrNull();
        $this->assertInstanceOf(Vendor::class, $vendor2);

        // should update the position
        $this->assertEquals(4, $import->position);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];

        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(3, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should update the vendor numbered "VEND-0003"
        self::$vendor->refresh();
        $this->assertEquals('Test 3', self::$vendor->name);

        // should update the position
        $this->assertEquals(4, $import->position);
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $vendor = new Vendor();
        $vendor->name = 'Delete Test';
        $vendor->saveOrFail();

        $vendor2 = new Vendor();
        $vendor2->name = 'Delete Test 2';
        $vendor2->saveOrFail();

        $mapping = ['name', 'number'];
        $lines = [
            [
                'Delete Test',
                '',
            ],
            [
                '',
                $vendor2->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(Vendor::where('name', 'Delete Test')->oneOrNull());
        $this->assertNull(Vendor::where('number', $vendor2->number)->oneOrNull());
    }

    protected function getLines(): array
    {
        return [
            [
                'Test',
                '',
                'TX',
                0,
            ],
            [
                'Test 2',
                'VEND-0002',
            ],
            [
                'Test 3',
                'VEND-0003',
                '',
                1,
            ],
            // this should not be imported
            [],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'name',
            'number',
            'state',
            'active',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'vendor';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'name' => 'Test',
                'state' => 'TX',
                'active' => false,
            ],
            [
                '_operation' => 'create',
                'name' => 'Test 2',
                'number' => 'VEND-0002',
                'state' => null,
            ],
            [
                '_operation' => 'create',
                'name' => 'Test 3',
                'number' => 'VEND-0003',
                'state' => null,
                'active' => true,
            ],
            [
                '_operation' => 'create',
                'name' => null,
                'number' => null,
                'state' => null,
            ],
        ];
    }
}
