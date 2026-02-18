<?php

namespace App\Tests\Imports\Importers;

use App\CashApplication\Models\CreditBalanceAdjustment;
use App\Imports\Importers\Spreadsheet\CreditBalanceAdjustmentImporter;
use App\Imports\Models\Import;
use Mockery;

class CreditBalanceAdjustmentImporterTest extends ImporterTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
    }

    protected function getImporter(): CreditBalanceAdjustmentImporter
    {
        return self::getService('test.importer_factory')->get('credit_balance_adjustment');
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
        $this->assertEquals(2, $result->getNumCreated());
        $this->assertEquals(0, $result->getNumUpdated());

        // should create an adjustment
        $adjustments = CreditBalanceAdjustment::where('customer', self::$customer->id())
            ->first(100);
        $this->assertCount(2, $adjustments);
        $adjustment = $adjustments[0];
        $this->assertInstanceOf(CreditBalanceAdjustment::class, $adjustment);

        $expected = [
            'id' => $adjustment->id(),
            'object' => 'credit_balance_adjustment',
            'customer' => self::$customer->id(),
            'date' => mktime(6, 0, 0, 8, 1, 2014),
            'currency' => 'usd',
            'amount' => 1000.1,
            'notes' => null,
            'created_at' => $adjustment->created_at,
        ];
        $this->assertEquals($expected, $adjustment->toArray());

        // should create a negative adjustment
        $adjustment = $adjustments[1];
        $this->assertInstanceOf(CreditBalanceAdjustment::class, $adjustment);

        $expected = [
            'id' => $adjustment->id(),
            'object' => 'credit_balance_adjustment',
            'customer' => self::$customer->id(),
            'date' => mktime(6, 0, 0, 8, 1, 2014),
            'currency' => 'usd',
            'amount' => -500.0,
            'notes' => null,
            'created_at' => $adjustment->created_at,
        ];
        $this->assertEquals($expected, $adjustment->toArray());

        // should update the position
        $this->assertEquals(2, $import->position);
    }

    protected function getLines(): array
    {
        return [
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '$1,000.10',
            ],
            [
                self::$customer->name,
                'Aug-01-2014',
                'USD',
                '-$500',
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'customer',
            'date',
            'currency',
            'amount',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'credit_balance_adjustment';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                ],
                'date' => mktime(6, 0, 0, 8, 1, 2014),
                'currency' => 'USD',
                'amount' => 1000.1,
            ],
            [
                '_operation' => 'create',
                'customer' => [
                    'name' => 'Sherlock',
                ],
                'date' => mktime(6, 0, 0, 8, 1, 2014),
                'currency' => 'USD',
                'amount' => -500.0,
            ],
        ];
    }
}
