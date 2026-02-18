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
use App\Core\Orm\Relation\BelongsToMany;
use App\Core\Orm\Relation\Pivot;
use App\Tests\Core\Orm\Models\Group;
use App\Tests\Core\Orm\Models\Person;

class BelongsToManyTest extends ModelTestCase
{
    private static Mockery\MockInterface $driver;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$driver = Mockery::mock(DriverInterface::class);
        Model::setDriver(self::$driver);
    }

    public function testInitQuery(): void
    {
        $person = new Person(['id' => 10]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', Group::class, 'group_id');

        $this->assertEquals('group_person', $relation->getTablename());

        $query = $relation->getQuery();
        $this->assertInstanceOf(Group::class, $query->getModel());
        $joins = $query->getJoins();
        $this->assertCount(1, $joins);
        $this->assertInstanceOf(Pivot::class, $joins[0][0]);
        $this->assertEquals('group_person', $joins[0][0]->getTablename());
        $this->assertEquals('group_id', $joins[0][1]);
        $this->assertEquals('id', $joins[0][2]);
        $this->assertEquals(['group_person.person_id = 10'], $query->getWhere());
    }

    public function testGetResults(): void
    {
        $person = new Person(['id' => 10]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', Group::class, 'group_id');

        self::$driver->shouldReceive('queryModels')
            ->andReturn([['id' => 11], ['id' => 12]]);

        $result = $relation->getResults();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        foreach ($result as $m) {
            $this->assertInstanceOf(Group::class, $m);
        }

        $this->assertEquals(11, $result[0]->id());
        $this->assertEquals(12, $result[1]->id());
    }

    public function testEmpty(): void
    {
        $person = new Person();

        $relation = new BelongsToMany($person, 'person_id', 'group_person', Group::class, 'group_id');

        $this->assertNull($relation->getResults());
    }

    public function testSave(): void
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', Group::class, 'group_id');

        $group = new Group(['id' => 5]);
        $group->name = 'Test'; /* @phpstan-ignore-line */

        self::$driver->shouldReceive('updateModel')
            ->withArgs([$group, ['name' => 'Test']])
            ->andReturn(true)
            ->once();

        self::$driver->shouldReceive('createModel')
            ->andReturnUsing(function ($model, $params) {
                $this->assertInstanceOf(Pivot::class, $model);
                $this->assertEquals(['person_id' => 2, 'group_id' => 5], $params);

                return true;
            })
            ->once();

        self::$driver->shouldReceive('getCreatedID')
            ->andReturn(1);

        $this->assertEquals($group, $relation->save($group));

        $this->assertTrue($group->persisted());

        // verify pivot
        $pivot = $group->pivot; /* @phpstan-ignore-line */
        $this->assertInstanceOf(Pivot::class, $pivot);
        $this->assertEquals('group_person', $pivot->getTablename());
        $this->assertTrue($pivot->persisted());
    }

    public function testCreate(): void
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', Group::class, 'group_id');

        self::$driver->shouldReceive('createModel')
            ->andReturn(true);

        self::$driver->shouldReceive('getCreatedID')
            ->andReturn(1);

        $group = $relation->create(['name' => 'Test']);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertTrue($group->persisted());

        // verify pivot
        $pivot = $group->pivot; /* @phpstan-ignore-line */
        $this->assertInstanceOf(Pivot::class, $pivot);
        $this->assertEquals('group_person', $pivot->getTablename());
        $this->assertTrue($pivot->persisted());
    }

    public function testAttach(): void
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', Group::class, 'group_id');

        $group = new Group(['id' => 3]);

        self::$driver->shouldReceive('createModel')
            ->andReturnUsing(function ($model, $params) {
                $this->assertInstanceOf(Pivot::class, $model);
                $this->assertEquals(['person_id' => 2, 'group_id' => 3], $params);

                return true;
            })
            ->once();

        self::$driver->shouldReceive('getCreatedID')
            ->andReturn(1);

        $this->assertEquals($relation, $relation->attach($group));

        $pivot = $group->pivot; /* @phpstan-ignore-line */
        $this->assertInstanceOf(Pivot::class, $pivot);
        $this->assertEquals('group_person', $pivot->getTablename());
        $this->assertTrue($pivot->persisted());
    }

    public function testDetach(): void
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', Group::class, 'group_id');

        $group = new Group();
        $group->person_id = 2; /* @phpstan-ignore-line */
        $group->pivot = Mockery::mock(); /* @phpstan-ignore-line */
        $group->pivot->shouldReceive('delete')->once();

        $this->assertEquals($relation, $relation->detach($group));
    }

    public function testSync(): void
    {
        $person = new Person(['id' => 2]);

        $relation = new BelongsToMany($person, 'person_id', 'group_person', Group::class, 'group_id');

        self::$driver = Mockery::mock(DriverInterface::class);

        self::$driver->shouldReceive('count')
            ->andReturn(3);

        self::$driver->shouldReceive('queryModels')
            ->andReturnUsing(function ($query) {
                $this->assertInstanceOf(Pivot::class, $query->getModel());
                $this->assertEquals('group_person', $query->getModel()->getTablename());
                $this->assertEquals(['group_id NOT IN (1,2,3)', 'person_id' => 2], $query->getWhere());

                return [['id' => 3], ['id' => 4], ['id' => 5]];
            });

        self::$driver->shouldReceive('deleteModel')
            ->andReturn(true)
            ->times(3);

        Model::setDriver(self::$driver);

        $this->assertEquals($relation, $relation->sync([1, 2, 3]));
    }
}
