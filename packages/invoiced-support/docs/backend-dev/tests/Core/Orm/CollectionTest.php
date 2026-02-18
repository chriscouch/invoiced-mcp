<?php

namespace App\Tests\Core\Orm;

use App\Tests\Core\Orm\Models\TestModel;
use Exception;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use App\Core\Orm\Collection;

class CollectionTest extends MockeryTestCase
{
    public function testArrayAccess(): void
    {
        $model1 = new TestModel();
        $model2 = new TestModel();
        $model3 = new TestModel();
        $collection = new Collection([$model1, $model2, $model3]);

        $this->assertEquals($model1, $collection[0]);
        $this->assertEquals($model2, $collection[1]);
        $this->assertEquals($model3, $collection[2]);

        $this->assertTrue(isset($collection[0]));
    }

    public function testArrayAccessNoSet(): void
    {
        $model1 = new TestModel();
        $model2 = new TestModel();
        $model3 = new TestModel();
        $collection = new Collection([$model1, $model2, $model3]);
        $this->expectException(Exception::class);
        $collection[4] = new TestModel();
    }

    public function testArrayAccessNoUnset(): void
    {
        $model1 = new TestModel();
        $model2 = new TestModel();
        $model3 = new TestModel();
        $collection = new Collection([$model1, $model2, $model3]);
        $this->expectException(Exception::class);
        unset($collection[4]);
    }

    public function testIterator(): void
    {
        $model1 = new TestModel();
        $model2 = new TestModel();
        $model3 = new TestModel();
        $collection = new Collection([$model1, $model2, $model3]);

        $total = 0;
        foreach ($collection as $n) {
            ++$total;
        }
        $this->assertEquals(3, $total);
    }

    public function testCount(): void
    {
        $model1 = new TestModel();
        $model2 = new TestModel();
        $model3 = new TestModel();
        $collection = new Collection([$model1, $model2, $model3]);
        $this->assertCount(3, $collection);
    }
}
