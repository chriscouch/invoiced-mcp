<?php

namespace App\Tests\Imports\Importers;

use App\Imports\Importers\Spreadsheet\PlanImporter;
use App\Imports\Models\Import;
use App\SubscriptionBilling\Models\Plan;
use Mockery;

class PlanImporterTest extends ImporterTestBase
{
    protected function getImporter(): PlanImporter
    {
        return self::getService('test.importer_factory')->get('plan');
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

        // should create a plan
        /** @var Plan $plan */
        $plan = Plan::getCurrent('test-plan');
        $this->assertInstanceOf(Plan::class, $plan);

        $expected = [
            'id' => 'test-plan',
            'object' => 'plan',
            'name' => 'Test Plan',
            'currency' => 'usd',
            'amount' => 5000,
            'interval_count' => 1,
            'interval' => 'month',
            'description' => null,
            'notes' => null,
            'quantity_type' => 'constant',
            'pricing_mode' => Plan::PRICING_PER_UNIT,
            'tiers' => null,
            'catalog_item' => null,
            'metadata' => (object) ['test' => '1234'],
        ];

        $arr = $plan->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertEquals(self::$company->id(), $plan->tenant_id);

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
        $lines[0][4] = '$6,000';

        $records = $importer->build($mapping, $lines, $options, $import);
        $result = $importer->run($records, $options, $import);

        // verify result
        $this->assertEquals(0, $result->getNumCreated());
        $this->assertEquals(1, $result->getNumUpdated());

        // should create a plan
        /** @var Plan $plan */
        $plan = Plan::getCurrent('test-plan');
        $this->assertInstanceOf(Plan::class, $plan);

        $expected = [
            'id' => 'test-plan',
            'object' => 'plan',
            'name' => 'Test Plan',
            'currency' => 'usd',
            'amount' => 6000,
            'interval_count' => 1,
            'interval' => 'month',
            'description' => null,
            'notes' => null,
            'quantity_type' => 'constant',
            'pricing_mode' => Plan::PRICING_PER_UNIT,
            'tiers' => null,
            'catalog_item' => null,
            'metadata' => (object) ['test' => '1234'],
        ];

        $arr = $plan->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);

        $this->assertEquals(self::$company->id(), $plan->tenant_id);

        // should update the position
        $this->assertEquals(1, $import->position);
    }

    public function testRunDelete(): void
    {
        $importer = $this->getImporter();

        $plan = new Plan();
        $plan->name = 'Delete Test';
        $plan->amount = 10;
        $plan->interval = 'month';
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $mapping = ['id'];
        $lines = [
            [
                $plan->id,
            ],
        ];
        $import = $this->getImport();

        $options = ['operation' => 'delete'];
        $records = $importer->build($mapping, $lines, $options, $import);
        $importer->run($records, $options, $import);

        // verify result
        $this->assertNull(Plan::getCurrent($plan->id));
    }

    protected function getLines(): array
    {
        return [
            [
                'test-plan',
                'Test Plan',
                '1',
                'month',
                '$5,000',
                '1234',
            ],
        ];
    }

    protected function getMapping(): array
    {
        return [
            'id',
            'name',
            'interval_count',
            'interval',
            'amount',
            'metadata.test',
        ];
    }

    protected function getImport(): Import
    {
        $import = Mockery::mock(Import::class.'[save]');
        $import->shouldReceive('save')
            ->andReturn(true);
        $import->type = 'plan';

        return $import;
    }

    protected function getExpectedAfterBuild(): array
    {
        return [
            [
                '_operation' => 'create',
                'id' => 'test-plan',
                'name' => 'Test Plan',
                'interval_count' => 1,
                'interval' => 'month',
                'amount' => 5000.0,
                'metadata' => (object) ['test' => '1234'],
            ],
        ];
    }
}
