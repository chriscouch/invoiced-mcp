<?php

namespace App\Tests\Imports\Importers;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorPayment;
use App\Imports\Importers\Spreadsheet\VendorPaymentImporter;
use App\Imports\Models\Import;
use Carbon\CarbonImmutable;
use Mockery;

class VendorPaymentImporterTest extends ImporterTestBase
{
    private static Bill $bill2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasVendor();
        self::hasBill();
        self::$bill2 = self::getTestDataFactory()->createBill(self::$vendor);
    }

    protected function getImporter(): VendorPaymentImporter
    {
        return self::getService('test.importer_factory')->get('vendor_payment');
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
        $this->assertEquals(1, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(0, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should update the position
        $this->assertEquals(1, $import->position);

        // should create a vendor payment
        $vendorPayment = VendorPayment::where('vendor_id', self::$vendor)->one();
        $expected = [
            'amount' => 1000.0,
            'bank_account_id' => null,
            'card_id' => null,
            'created_at' => $vendorPayment->created_at,
            'currency' => 'usd',
            'date' => $vendorPayment->date,
            'expected_arrival_date' => null,
            'id' => $vendorPayment->id,
            'notes' => null,
            'number' => 'PAY-00001',
            'object' => 'vendor_payment',
            'payment_method' => 'other',
            'reference' => null,
            'updated_at' => $vendorPayment->updated_at,
            'vendor_id' => self::$vendor->id,
            'vendor_payment_batch_id' => null,
            'voided' => false,
        ];
        $this->assertEquals($expected, $vendorPayment->toArray());
    }

    public function testRunUpsert(): void
    {
        $importer = $this->getImporter();

        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $lines[0][2] = 600;
        $mapping[] = 'number';
        foreach ($lines as &$line) {
            $line[] = 'PAY-00001';
        }
        $import = $this->getImport();

        $options = ['operation' => 'upsert'];

        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(1, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should update the position
        $this->assertEquals(1, $import->position);

        // should update the vendor payment numbered "PAY-00001"
        $vendorPayment = VendorPayment::where('vendor_id', self::$vendor)
            ->where('number', 'PAY-00001')
            ->one();
        $this->assertInstanceOf(VendorPayment::class, $vendorPayment);
        $this->assertEquals(600, $vendorPayment->amount);
    }

    public function testRunVoid(): void
    {
        $importer = $this->getImporter();

        $vendor = new Vendor();
        $vendor->name = 'Void Test';
        $vendor->saveOrFail();

        $vendorPayment = new VendorPayment();
        $vendorPayment->vendor = $vendor;
        $vendorPayment->currency = 'usd';
        $vendorPayment->date = CarbonImmutable::now();
        $vendorPayment->amount = 100;
        $vendorPayment->saveOrFail();

        $mapping = ['number'];
        $lines = [
            [
                $vendorPayment->number,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'void'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertTrue($vendorPayment->refresh()->voided);
    }

    protected function getLines(): array
    {
        return [
            [
                'Test Vendor',
                '2024-01-22',
                1000,
                'usd',
                self::$bill->number,
                100,
            ],
            [
                'VEND-00001',
                '',
                '',
                '',
                self::$bill2->number,
                200,
            ],
            [
                'Test Vendor',
                '',
                '',
                '',
                self::$bill->number,
                300,
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'vendor',
            'date',
            'amount',
            'currency',
            'bill',
            'amount_applied',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'vendor_payment';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'vendor' => self::$vendor->id,
                'date' => '2024-01-22',
                'currency' => 'usd',
                'amount' => 1000,
                'applied_to' => [
                    [
                        'bill' => self::$bill->id,
                        'amount' => 100,
                    ],
                    [
                        'bill' => self::$bill2->id,
                        'amount' => 200,
                    ],
                    [
                        'bill' => self::$bill->id,
                        'amount' => 300,
                    ],
                ],
            ],
        ];
    }
}
