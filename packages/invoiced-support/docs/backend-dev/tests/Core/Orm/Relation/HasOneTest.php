<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace App\Tests\Core\Orm\Relation;

use App\Tests\Core\Orm\ModelTestCase;
use Mockery;
use App\Core\Orm\Driver\DriverInterface;
use App\Core\Orm\Model;
use App\Core\Orm\Relation\HasOne;
use App\Tests\Core\Orm\Models\Balance;
use App\Tests\Core\Orm\Models\Person;

class HasOneTest extends ModelTestCase
{
    public static Mockery\MockInterface $driver;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$driver = Mockery::mock(DriverInterface::class);
        Model::setDriver(self::$driver);
    }

    public function testInitQuery(): void
    {
        $person = new Person(['id' => 10]);

        $relation = new HasOne($person, 'id', Balance::class, 'person_id');

        $query = $relation->getQuery();
        $this->assertInstanceOf(Balance::class, $query->getModel());
        $this->assertEquals(['person_id' => 10], $query->getWhere());
        $this->assertEquals(1, $query->getLimit());
    }

    public function testGetResults(): void
    {
        $person = new Person(['id' => 10]);

        $relation = new HasOne($person, 'id', Balance::class, 'person_id');

        self::$driver->shouldReceive('queryModels')
            ->andReturn([['id' => 11]]);

        $result = $relation->getResults();
        $this->assertInstanceOf(Balance::class, $result);
        $this->assertEquals(11, $result->id());
    }

    public function testEmpty(): void
    {
        $person = new Person(['id' => null]);

        $relation = new HasOne($person, 'id', Balance::class, 'person_id');

        $this->assertNull($relation->getResults());
    }

    public function testSave(): void
    {
        $person = new Person(['id' => 100]);

        $relation = new HasOne($person, 'id', Balance::class, 'person_id');

        $balance = new Balance(['id' => 20]);
        $balance->refreshWith(['amount' => 200]);

        self::$driver->shouldReceive('updateModel')
            ->withArgs([$balance, ['person_id' => 100]])
            ->andReturn(true)
            ->once();

        $this->assertEquals($balance, $relation->save($balance));

        $this->assertTrue($balance->persisted());
    }

    public function testCreate(): void
    {
        $person = new Person(['id' => 100]);

        $relation = new HasOne($person, 'id', Balance::class, 'person_id');

        self::$driver->shouldReceive('createModel')
            ->andReturnUsing(function ($model, $params) {
                $this->assertInstanceOf(Balance::class, $model);
                $this->assertEquals(['amount' => 5000, 'person_id' => 100], $params);

                return true;
            })
            ->once();

        self::$driver->shouldReceive('getCreatedID')
            ->andReturn(1);

        $balance = $relation->create(['amount' => 5000]);

        $this->assertInstanceOf(Balance::class, $balance);
        $this->assertTrue($balance->persisted());
    }

    public function testAttach(): void
    {
        $person = new Person(['id' => 100]);

        $relation = new HasOne($person, 'id', Balance::class, 'person_id');

        $balance = new Balance();

        self::$driver->shouldReceive('createModel')
            ->withArgs([$balance, ['person_id' => 100]])
            ->andReturn(true)
            ->once();

        self::$driver->shouldReceive('getCreatedID')
            ->andReturn(1);

        $this->assertEquals($relation, $relation->attach($balance));

        $this->assertTrue($balance->persisted());
    }

    public function testDetach(): void
    {
        $person = new Person(['id' => 100]);

        $relation = new HasOne($person, 'id', Balance::class, 'person_id');

        self::$driver->shouldReceive('updateModel')
            ->andReturn(true)
            ->once();

        $this->assertEquals($relation, $relation->detach());
    }
}
