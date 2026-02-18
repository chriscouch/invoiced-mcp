<?php

namespace App\Tests\Exports;

use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\MemberACLExportJob;
use App\Exports\Exporters\CustomerExporter;
use App\Exports\Libs\ExportStorage;
use App\Exports\Models\Export;
use App\Tests\AppTestCase;
use Mockery;

class ExportJobTest extends AppTestCase
{
    private static Export $export;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testSetup(): void
    {
        // mock queueing operations
        $queue = Mockery::mock(Queue::class);
        $queue->shouldReceive('enqueue')->once();

        self::$export = MemberACLExportJob::create($queue, 'customer');

        // the export job should be queued
        $this->assertEquals('customer', self::$export->type);
        $this->assertEquals(Export::PENDING, self::$export->status);
    }

    /**
     * @depends testSetup
     */
    public function testExecute(): void
    {
        $storage = Mockery::mock(ExportStorage::class);
        $storage->shouldReceive('persist')->andReturn('https://example.com/test');
        $database = self::getService('test.database');
        $helper = self::getService('test.attribute_helper');
        $translator = self::getService('translator');
        $factory = self::getService('test.list_query_builder_factory');
        $exporter = new CustomerExporter($storage, $database, $helper, $translator, $factory);
        $exporter->build(self::$export, []);

        $this->assertEquals(Export::SUCCEEDED, self::$export->refresh()->status);
        $this->assertNotNull(self::$export->download_url);
    }
}
