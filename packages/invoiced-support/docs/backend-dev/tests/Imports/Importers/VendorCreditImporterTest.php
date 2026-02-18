<?php

namespace App\Tests\Imports\Importers;

use App\AccountsPayable\Enums\PayableDocumentSource;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorCredit;
use App\Imports\Importers\Spreadsheet\VendorCreditImporter;
use App\Imports\Models\Import;
use Carbon\CarbonImmutable;
use Mockery;

class VendorCreditImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasVendor();
    }

    protected function getImporter(): VendorCreditImporter
    {
        return self::getService('test.importer_factory')->get('vendor_credit');
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

        // should create a vendor credit
        $vendorCredit = VendorCredit::where('vendor_id', self::$vendor)
            ->where('number', 'CN-00001')
            ->one();
        $this->assertInstanceOf(VendorCredit::class, $vendorCredit);
        $this->assertEquals(400, $vendorCredit->total);
        $this->assertEquals(PayableDocumentSource::Imported, $vendorCredit->source);

        // should create a second vendor credit
        $vendorCredit = VendorCredit::where('vendor_id', self::$vendor)
            ->where('number', 'CN-00002')
            ->one();
        $this->assertInstanceOf(VendorCredit::class, $vendorCredit);
        $this->assertEquals(200, $vendorCredit->total);
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $lines[0][5] = 600;
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];

        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(2, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should update the position
        $this->assertEquals(2, $import->position);

        // should update the vendor credit numbered "CN-00001"
        $vendorCredit = VendorCredit::where('vendor_id', self::$vendor)
            ->where('number', 'CN-00001')
            ->one();
        $this->assertInstanceOf(VendorCredit::class, $vendorCredit);
        $this->assertEquals(900, $vendorCredit->total);
    }

    public function testRunVoid(): void
    {
        $importer = $this->getImporter();

        $vendor = new Vendor();
        $vendor->name = 'Void Test';
        $vendor->saveOrFail();

        $vendorCredit = new VendorCredit();
        $vendorCredit->vendor = $vendor;
        $vendorCredit->number = 'CN-000005';
        $vendorCredit->currency = 'usd';
        $vendorCredit->date = CarbonImmutable::now();
        $vendorCredit->total = 100;
        $vendorCredit->saveOrFail();

        $mapping = ['vendor', 'number'];
        $lines = [
            [
                'Void Test',
                'CN-000005',
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'void'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertTrue($vendorCredit->refresh()->voided);
    }

    protected function getLines(): array
    {
        return [
            [
                'Test Vendor',
                'CN-00001',
                '2024-01-22',
                'usd',
                'First Line',
                100,
            ],
            [
                'VEND-00001',
                'CN-00002',
                '2024-12-31',
                'usd',
                'Test Line',
                200,
            ],
            [
                'Test Vendor',
                'CN-00001',
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
        $import->type = 'vendor_credit';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'vendor' => self::$vendor->id,
                'number' => 'CN-00001',
                'date' => '2024-01-22',
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
                'number' => 'CN-00002',
                'date' => '2024-12-31',
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
