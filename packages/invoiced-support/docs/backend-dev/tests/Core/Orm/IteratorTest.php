<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace App\Tests\Core\Orm;

use Exception;
use Mockery;
use OutOfBoundsException;
use App\Core\Orm\Driver\DriverInterface;
use App\Core\Orm\Iterator;
use App\Core\Orm\Query;
use App\Tests\Core\Orm\Models\IteratorTestModel;

class IteratorTest extends ModelTestCase
{
    public static DriverInterface $driver;
    public static Query $query;
    public static Iterator $iterator;
    public static int $start = 10;
    public static int $limit = 50;
    public static int $count = 123;
    public static bool $noResults = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $driver = Mockery::mock(DriverInterface::class);

        $driver->shouldReceive('queryModels')
            ->andReturnUsing(function ($query) {
                if (IteratorTest::$noResults) {
                    return [];
                }

                $range = range($query->getStart(), $query->getStart() + $query->getLimit() - 1);

                foreach ($range as &$i) {
                    $i = ['id' => $i];
                }

                return $range;
            });

        $driver->shouldReceive('count')
            ->andReturnUsing(function () {
                return IteratorTest::$count;
            });

        self::$driver = $driver;
        IteratorTestModel::setDriver(self::$driver);

        self::$query = new Query(IteratorTestModel::class);
        self::$query->start(self::$start)
            ->limit(self::$limit);
        self::$iterator = new Iterator(self::$query);
    }

    protected function tearDown(): void
    {
        self::$count = 123;
        self::$noResults = false;
        self::$iterator->rewind();
    }

    public function testGetQuery(): void
    {
        $this->assertEquals(self::$query, self::$iterator->getQuery());

        // the default sorting should be by ID in ascending order
        $this->assertEquals([['id', 'asc']], self::$iterator->getQuery()->getSort());
    }

    public function testKey(): void
    {
        $this->assertEquals(self::$start, self::$iterator->key());
    }

    public function testValid(): void
    {
        $this->assertTrue(self::$iterator->valid());
    }

    public function testNext(): void
    {
        for ($i = self::$start; $i < self::$limit + 1; ++$i) {
            self::$iterator->next();
        }

        $this->assertEquals(self::$limit + 1, self::$iterator->key());
    }

    public function testRewind(): void
    {
        self::$iterator->next();
        $this->assertNotEquals(self::$start, self::$iterator->key());
        self::$iterator->rewind();
        $this->assertEquals(self::$start, self::$iterator->key());
    }

    public function testCurrent(): void
    {
        self::$iterator->rewind();

        $count = IteratorTestModel::count();
        for ($i = self::$start; $i < $count + 1; ++$i) {
            $current = self::$iterator->current();
            if ($i < $count) {
                $this->assertInstanceOf(IteratorTestModel::class, $current);
                $this->assertEquals($i, $current->id());
            } else {
                $this->assertNull($current);
            }

            self::$iterator->next();
        }
    }

    public function testNotValid(): void
    {
        self::$iterator->rewind();
        for ($i = self::$start; $i < IteratorTestModel::count() + 1; ++$i) {
            self::$iterator->next();
        }

        $this->assertFalse(self::$iterator->valid());
    }

    public function testForeach(): void
    {
        $i = self::$start;
        $n = 0;
        foreach (self::$iterator as $k => $model) {
            $this->assertEquals($i, $k);
            $this->assertInstanceOf(IteratorTestModel::class, $model);
            $this->assertEquals($i, $model->id());
            ++$i;
            ++$n;
        }

        // last model ID that should have been produced
        $this->assertEquals(IteratorTestModel::count(), $i);

        // total # of records we should have iterated over
        $this->assertEquals(IteratorTestModel::count() - self::$start, $n);
    }

    public function testForeachChangingCount(): void
    {
        self::$count = 200;

        $i = self::$start;
        $n = 0;
        foreach (self::$iterator as $k => $model) {
            $this->assertEquals($i, $k);
            $this->assertInstanceOf(IteratorTestModel::class, $model);
            $this->assertEquals($i, $model->id());
            ++$i;
            ++$n;

            // simulate increasing the # of records midway
            if (51 == $i) {
                self::$count = 300;
                $this->assertCount(300, self::$iterator);
                // simulate decreasing the # of records midway
            } elseif (101 == $i) {
                self::$count = 26;
                $this->assertCount(26, self::$iterator);

                // The assumption is that the deleted records were
                // before the pointer. In order to not skip over
                // potential records the pointer is shifted
                // backwards. After the shift there should be N
                // records left to iterate over.
                $this->assertEquals(0, self::$iterator->key());
                $i = 1;
            }
        }

        // last model ID that should have been produced
        $this->assertEquals(26, $i);

        // total # of records we should have iterated over
        $this->assertEquals(116, $n);
    }

    public function testForeachFromZero(): void
    {
        $start = 0;
        $limit = 101;
        $query = new Query(IteratorTestModel::class);
        $query->limit($limit);
        $iterator = new Iterator($query);

        $i = $start;
        foreach ($iterator as $k => $model) {
            $this->assertEquals($i, $k);
            $this->assertInstanceOf(IteratorTestModel::class, $model);
            $this->assertEquals($i, $model->id());
            ++$i;
        }

        $this->assertEquals($i, IteratorTestModel::count());
    }

    public function testCount(): void
    {
        $this->assertCount(123, self::$iterator);
    }

    public function testOffsetExists(): void
    {
        $this->assertTrue(isset(self::$iterator[0]));
        $this->assertFalse(isset(self::$iterator[123]));
    }

    public function testOffsetGet(): void
    {
        $this->assertEquals(0, self::$iterator[0]?->id());
        $this->assertEquals(1, self::$iterator[1]?->id());
    }

    public function testToArray(): void
    {
        $query = new Query(IteratorTestModel::class);
        $query->limit(3);
        $iterator = new Iterator($query);

        $arr = $iterator->toArray();
        $this->assertEquals($iterator[0]?->id(), $arr[0]->id());
        $this->assertEquals($iterator[1]?->id(), $arr[1]->id());
        $this->assertEquals($iterator[2]?->id(), $arr[2]->id());

        $iterator->next();
        $arr = $iterator->toArray();
        $this->assertEquals($iterator[0]?->id(), $arr[0]->id());
        $this->assertEquals($iterator[1]?->id(), $arr[1]->id());
        $this->assertEquals($iterator[2]?->id(), $arr[2]->id());
    }

    public function testOffsetGetOOB(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $fail = self::$iterator[100000];
    }

    public function testOffsetSet(): void
    {
        $this->expectException(Exception::class);

        self::$iterator[0] = null; /* @phpstan-ignore-line */
    }

    public function testOffsetUnset(): void
    {
        $this->expectException(Exception::class);

        unset(self::$iterator[0]);
    }

    public function testQueryModelsMismatchCount(): void
    {
        // simulate the queryModels() method acting up
        self::$noResults = true;

        $numIterations = 0;
        foreach (self::$iterator as $model) {
            ++$numIterations;
        }

        // when the end of the results is reached then the loop
        // should terminate producing no iterations
        $this->assertEquals(0, $numIterations);
    }
}
