<?php

namespace App\Tests\Core\Search;

use App\AccountsReceivable\Models\Customer;
use App\Core\Search\Interfaces\IndexInterface;
use App\Core\Search\Libs\Strategy\InPlace;
use App\Tests\AppTestCase;
use Mockery;
use SplFixedArray;

class InPlaceStrategyTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testGetDiff(): void
    {
        $strategy = $this->getStrategy();

        $a = SplFixedArray::fromArray([1, 2, 3, 4, 5]);
        $b = SplFixedArray::fromArray([2, 3, 5]);

        $diff = $strategy->getDiff($a, $b);
        $this->assertInstanceOf(SplFixedArray::class, $diff);
        $this->assertEquals([1, 4], $diff->toArray());

        $a = SplFixedArray::fromArray([1, 2, 3, 4, 5]);
        $b = SplFixedArray::fromArray([]);

        $diff = $strategy->getDiff($a, $b);
        $this->assertInstanceOf(SplFixedArray::class, $diff);
        $this->assertEquals([1, 2, 3, 4, 5], $diff->toArray());

        $a = SplFixedArray::fromArray([2, 3, 5, 6]);
        $b = SplFixedArray::fromArray([1, 2, 3, 4, 5]);

        $diff = $strategy->getDiff($a, $b);
        $this->assertInstanceOf(SplFixedArray::class, $diff);
        $this->assertEquals([6], $diff->toArray());
    }

    public function testSliceAndImplode(): void
    {
        $strategy = $this->getStrategy();

        $a = SplFixedArray::fromArray([1, 2, 3, 4, 5]);
        $this->assertEquals('2,3,4', $strategy->sliceAndImplode($a, 1, 3, ','));

        $this->assertEquals('1,2,3,4,5', $strategy->sliceAndImplode($a, 0, 500, ','));
    }

    public function testRun(): void
    {
        $strategy = $this->getStrategy();

        $ids = SplFixedArray::fromArray([-1, -2, -3, -4]);

        $index = Mockery::mock(IndexInterface::class);
        $index->shouldReceive('getIds')
            ->andReturn($ids);
        $index->shouldReceive('deleteDocument')
            ->times(4);

        $index->shouldReceive('insertDocument')
            ->times(1);

        $strategy->run(self::$company, Customer::class, $index);
    }

    private function getStrategy(): InPlace
    {
        return new InPlace(self::getService('test.database'), self::getService('test.tenant'));
    }
}
