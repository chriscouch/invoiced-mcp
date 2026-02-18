<?php

namespace App\Tests\Core\Orm\Relation;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Tests\Core\Orm\Models\TestModel;

class RelationTest extends MockeryTestCase
{
    public function testGetLocalModel(): void
    {
        $model = Mockery::mock(Model::class);
        $relation = new TestAbstractRelation($model, 'user_id', TestModel::class, 'id');

        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testGetLocalKey(): void
    {
        $model = Mockery::mock(Model::class);
        $relation = new TestAbstractRelation($model, 'user_id', TestModel::class, 'id');

        $this->assertEquals('user_id', $relation->getLocalKey());
    }

    public function testGetForeignModel(): void
    {
        $model = Mockery::mock(Model::class);
        $relation = new TestAbstractRelation($model, 'user_id', TestModel::class, 'id');

        $this->assertEquals(TestModel::class, $relation->getForeignModel());
    }

    public function testGetForeignKey(): void
    {
        $model = Mockery::mock(Model::class);
        $relation = new TestAbstractRelation($model, 'user_id', TestModel::class, 'id');

        $this->assertEquals('id', $relation->getForeignKey());
    }

    public function testGetQuery(): void
    {
        $model = Mockery::mock(Model::class);
        $relation = new TestAbstractRelation($model, 'user_id', TestModel::class, 'id');

        $query = $relation->getQuery();
        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals(['test' => true], $query->getWhere());
    }

    public function testCallOnQuery(): void
    {
        $model = Mockery::mock(Model::class);
        $relation = new TestAbstractRelation($model, 'user_id', TestModel::class, 'id');

        $query = $relation->where(['name' => 'Bob']); /* @phpstan-ignore-line */

        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals(['test' => true, 'name' => 'Bob'], $query->getWhere());
    }
}
