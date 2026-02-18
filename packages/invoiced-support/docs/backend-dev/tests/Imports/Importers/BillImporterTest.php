<?php

namespace App\Tests\Imports\Importers;

use App\AccountsPayable\Enums\PayableDocumentSource;
use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\Imports\Importers\Spreadsheet\BillImporter;
use App\Imports\Models\Import;
use Carbon\CarbonImmutable;
use Mockery;

class BillImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasVendor();
    }

    protected function getImporter(): BillImporter
    {
        return self::getService('test.importer_factory')->get('bill');
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

        // should update the position
        $this->assertEquals(2, $import->position);

        // should create a bill
        $bill = Bill::where('vendor_id', self::$vendor)
            ->where('number', 'INV-00001')
            ->one();
        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertEquals(400, $bill->total);
        $this->assertEquals(PayableDocumentSource::Imported, $bill->source);

        // should create a second bill
        $bill = Bill::where('vendor_id', self::$vendor)
            ->where('number', 'INV-00002')
            ->one();
        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertEquals(200, $bill->total);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $lines[0][6] = 600;
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];

        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(2, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should update the position
        $this->assertEquals(2, $import->position);

        // should update the bill numbered "INV-00001"
        $bill = Bill::where('vendor_id', self::$vendor)
            ->where('number', 'INV-00001')
            ->one();
        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertEquals(900, $bill->total);
    }

    public function testRunVoid(): void
    {
        $importer = $this->getImporter();

        $vendor = new Vendor();
        $vendor->name = 'Void Test';
        $vendor->saveOrFail();

        $bill = new Bill();
        $bill->vendor = $vendor;
        $bill->number = 'INV-000005';
        $bill->currency = 'usd';
        $bill->date = CarbonImmutable::now();
        $bill->total = 100;
        $bill->saveOrFail();

        $mapping = ['vendor', 'number'];
        $lines = [
            [
                'Void Test',
                'INV-000005',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'void'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertTrue($bill->refresh()->voided);
    }

    protected function getLines(): array
    {
        return [
            [
                'Test Vendor',
                'INV-00001',
                '2024-01-22',
                '2024-02-22',
                'usd',
                'First Line',
                100,
            ],
            [
                'VEND-00001',
                'INV-00002',
                '2024-12-31',
                '',
                'usd',
                'Test Line',
                200,
            ],
            [
                'Test Vendor',
                'INV-00001',
                '',
                '',
                '',
                'Second Line',
                300,
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'vendor',
            'number',
            'date',
            'due_date',
            'currency',
            'description',
            'amount',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'bill';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'vendor' => self::$vendor->id,
                'number' => 'INV-00001',
                'date' => '2024-01-22',
                'due_date' => '2024-02-22',
                'currency' => 'usd',
                'line_items' => [
                    [
                        'description' => 'First Line',
                        'amount' => 100,
                    ],
                    [
                        'description' => 'Second Line',
                        'amount' => 300,
                    ],
                ],
            ],
            [
                '_operation' => 'create',
                'vendor' => self::$vendor->id,
                'number' => 'INV-00002',
                'date' => '2024-12-31',
                'due_date' => null,
                'currency' => 'usd',
                'line_items' => [
                    [
                        'description' => 'Test Line',
                        'amount' => 200,
                    ],
                ],
            ],
        ];
    }
}
