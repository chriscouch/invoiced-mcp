<?php

namespace App\Tests\Core\Search;

use App\Core\Search\Driver\Elasticsearch\ElasticsearchDriver;
use App\Core\Search\Driver\Elasticsearch\ElasticsearchIndexCleaner;
use App\Tests\AppTestCase;
use Mockery;

class ElasticsearchIndexCleanerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testDeleteOrphaned(): void
    {
        $driver = Mockery::mock(ElasticsearchDriver::class);
        $driver->shouldReceive('getHosts')
            ->andReturn(['127.0.0.1']);
        $driver->shouldReceive('getTenants')
            ->andReturn(['-1', '-2', '-3', '-4']);
        $driver->shouldReceive('removeTenants')
            ->withArgs([['-1', '-2', '-3', '-4']])
            ->once();

        $cleaner = new ElasticsearchIndexCleaner($driver);

        $cleaner->deleteOrphaned();
    }
}
