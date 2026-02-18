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
use App\Core\Orm\Relation\BelongsTo;
use App\Tests\Core\Orm\Models\Category;
use App\Tests\Core\Orm\Models\Post;

class BelongsToTest extends ModelTestCase
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
        $post = new Post();
        $post->category_id = 10; /* @phpstan-ignore-line */

        $relation = new BelongsTo($post, 'category_id', Category::class, 'id');

        $query = $relation->getQuery();
        $this->assertInstanceOf(Category::class, $query->getModel());
        $this->assertEquals(['id' => 10], $query->getWhere());
        $this->assertEquals(1, $query->getLimit());
    }

    public function testGetResults(): void
    {
        $post = new Post();
        $post->category_id = 10; /* @phpstan-ignore-line */

        $relation = new BelongsTo($post, 'category_id', Category::class, 'id');

        self::$driver->shouldReceive('queryModels')
            ->andReturn([['id' => 11]]);

        $result = $relation->getResults();
        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals(11, $result->id());
    }

    public function testEmpty(): void
    {
        $post = new Post();
        $post->category_id = null; /* @phpstan-ignore-line */

        $relation = new BelongsTo($post, 'category_id', Category::class, 'id');

        $this->assertNull($relation->getResults());
    }

    public function testSave(): void
    {
        $post = new Post(['id' => 100]);
        $post->refreshWith(['category_id' => null]);

        $relation = new BelongsTo($post, 'category_id', Category::class, 'id');

        $category = new Category(['id' => 20]);
        $category->name = 'Test'; /* @phpstan-ignore-line */

        self::$driver->shouldReceive('updateModel')
            ->withArgs([$category, ['name' => 'Test']])
            ->andReturn(true)
            ->once();

        self::$driver->shouldReceive('updateModel')
            ->withArgs([$post, ['category_id' => 20]])
            ->andReturn(true)
            ->once();

        $this->assertEquals($category, $relation->save($category));

        $this->assertTrue($category->persisted());
        $this->assertTrue($post->persisted());
    }

    public function testCreate(): void
    {
        $post = new Post();
        $post->category_id = null; /* @phpstan-ignore-line */

        $relation = new BelongsTo($post, 'category_id', Category::class, 'id');

        self::$driver->shouldReceive('createModel')
            ->andReturn(true)
            ->once();

        self::$driver->shouldReceive('getCreatedID')
            ->andReturn(1);

        $category = $relation->create(['name' => 'Test']);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertTrue($category->persisted());

        $this->assertTrue($post->persisted());
    }

    public function testAttach(): void
    {
        $post = new Post();
        $post->category_id = null; /* @phpstan-ignore-line */

        $relation = new BelongsTo($post, 'category_id', Category::class, 'id');

        $category = new Category(['id' => 10]);

        self::$driver->shouldReceive('createModel')
            ->withArgs([$post, ['category_id' => 10]])
            ->andReturn(true)
            ->once();

        self::$driver->shouldReceive('getCreatedID')
            ->andReturn(1);

        $this->assertEquals($relation, $relation->attach($category));
        $this->assertTrue($post->persisted());
    }

    public function testDetach(): void
    {
        $post = new Post();
        $post->category_id = 10; /* @phpstan-ignore-line */

        $relation = new BelongsTo($post, 'category_id', Category::class, 'id');

        self::$driver->shouldReceive('updateModel')
            ->withArgs([$post, ['category_id' => null]])
            ->andReturn(true)
            ->once();

        $this->assertEquals($relation, $relation->detach());
        $this->assertTrue($post->persisted());
    }
}
