<?php

namespace App\Tests\Imports\Importers;

use App\AccountsReceivable\Models\Coupon;
use App\Imports\Importers\Spreadsheet\CouponImporter;
use App\Imports\Models\Import;
use Mockery;

class CouponImporterTest extends ImporterTestBase
{
    protected function getImporter(): CouponImporter
    {
        return self::getService('test.importer_factory')->get('coupon');
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

        // should create a coupon
        /** @var Coupon $coupon */
        $coupon = Coupon::getCurrent('test-coupon');
        $this->assertInstanceOf(Coupon::class, $coupon);

        $expected = [
            'id' => 'test-coupon',
            'object' => 'coupon',
            'name' => 'Test Coupon',
            'currency' => null,
            'is_percent' => true,
            'value' => 10,
            'duration' => 5,
            'exclusive' => null,
            'expiration_date' => null,
            'max_redemptions' => 50,
            'metadata' => (object) ['test' => '1234'],
        ];

        $arr = $coupon->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertEquals(self::$company->id(), $coupon->tenant_id);

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
        $this->assertEquals(0, $result->getNumCreated(), (string) json_encode($result->getFailures()));
        $this->assertEquals(1, $result->getNumUpdated(), (string) json_encode($result->getFailures()));

        // should create a tax rate
        /** @var Coupon $coupon */
        $coupon = Coupon::getCurrent('test-coupon');
        $this->assertInstanceOf(Coupon::class, $coupon);

        $expected = [
            'id' => 'test-coupon',
            'object' => 'coupon',
            'name' => 'Test Coupon',
            'currency' => null,
            'is_percent' => true,
            'value' => 12,
            'duration' => 5,
            'exclusive' => null,
            'expiration_date' => null,
            'max_redemptions' => 50,
            'metadata' => (object) ['test' => '1234'],
        ];

        $arr = $coupon->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertEquals(self::$company->id(), $coupon->tenant_id);

        // should update the position
        $this->assertEquals(1, $import->position);
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $coupon = new Coupon();
        $coupon->name = 'Delete Test';
        $coupon->value = 5;
        $coupon->saveOrFail();

        $mapping = ['id'];
        $lines = [
            [
                $coupon->id,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(Coupon::getCurrent($coupon->id));
    }

    protected function getLines(): array
    {
        return [
            [
                'test-coupon',
                'Test Coupon',
                '1',
                '10',
                '5',
                '50',
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
            'duration',
            'max_redemptions',
            'metadata.test',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'coupon';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'id' => 'test-coupon',
                'name' => 'Test Coupon',
                'is_percent' => '1',
                'value' => 10.0,
                'duration' => 5,
                'max_redemptions' => 50,
                'metadata' => (object) ['test' => '1234'],
            ],
        ];
    }
}
