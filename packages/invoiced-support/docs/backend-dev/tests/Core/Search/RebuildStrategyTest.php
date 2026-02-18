<?php

namespace App\Tests\Core\Search;

use App\AccountsReceivable\Models\Customer;
use App\Core\Search\Interfaces\DriverInterface;
use App\Core\Search\Interfaces\IndexInterface;
use App\Core\Search\Libs\Search;
use App\Core\Search\Libs\Strategy\Rebuild;
use App\Tests\AppTestCase;
use Mockery;

class RebuildStrategyTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testRun(): void
    {
        $index = Mockery::mock(IndexInterface::class);
        $index->shouldReceive('getName')
            ->andReturn('test');
        $index->shouldReceive('insertDocument')
            ->times(1);

        $index->shouldReceive('rename')
            ->once();

        $driver = Mockery::mock(DriverInterface::class);
        $driver->shouldReceive('createIndex')
            ->andReturn($index)
            ->once();

        $strategy = $this->getStrategy($driver);

        $strategy->run(self::$company, Customer::class, $index);
    }

    private function getStrategy(DriverInterface $driver): Rebuild
    {
        $search = Mockery::mock(Search::class);
        $search->shouldReceive('getDriver')
            ->andReturn($driver);

        return new Rebuild($search);
    }
}
