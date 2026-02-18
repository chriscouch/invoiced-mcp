<?php

namespace App\Tests\Core\Orm;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use App\Core\Orm\Relation\BelongsTo;
use App\Core\Orm\Relation\BelongsToMany;
use App\Core\Orm\Relation\HasMany;
use App\Core\Orm\Relation\HasOne;
use App\Core\Orm\Relation\Polymorphic;
use App\Core\Orm\Relation\Relationship;
use App\Tests\Core\Orm\Models\BankAccount;
use App\Tests\Core\Orm\Models\Card;
use App\Tests\Core\Orm\Models\InvalidRelationship;
use App\Tests\Core\Orm\Models\InvalidRelationship2;
use App\Tests\Core\Orm\Models\RelationshipTester;
use App\Tests\Core\Orm\Models\TestModel2;

class RelationshipTest extends TestCase
{
    public function testNotRelationship(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $model = new InvalidRelationship();
        Relationship::make($model, InvalidRelationship::definition()->get('name')); /* @phpstan-ignore-line */
    }

    public function testInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $model = new InvalidRelationship();
        Relationship::make($model, InvalidRelationship2::definition()->get('invalid_relationship')); /* @phpstan-ignore-line */
    }

    public function testHasOne(): void
    {
        $model = new RelationshipTester();
        $relation = Relationship::make($model, RelationshipTester::definition()->get('has_one')); /* @phpstan-ignore-line */

        $this->assertInstanceOf(HasOne::class, $relation);
        $this->assertEquals(TestModel2::class, $relation->getForeignModel());
        $this->assertEquals('relationship_tester_id', $relation->getForeignKey());
        $this->assertEquals('id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testHasMany(): void
    {
        $model = new RelationshipTester();
        $relation = Relationship::make($model, RelationshipTester::definition()->get('has_many')); /* @phpstan-ignore-line */

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals(TestModel2::class, $relation->getForeignModel());
        $this->assertEquals('relationship_tester_id', $relation->getForeignKey());
        $this->assertEquals('id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testBelongsTo(): void
    {
        $model = new RelationshipTester();
        $relation = Relationship::make($model, RelationshipTester::definition()->get('belongs_to')); /* @phpstan-ignore-line */

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(TestModel2::class, $relation->getForeignModel());
        $this->assertEquals('id', $relation->getForeignKey());
        $this->assertEquals('belongs_to_id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testBelongsToLegacy(): void
    {
        $model = new RelationshipTester();
        $relation = Relationship::make($model, RelationshipTester::definition()->get('belongs_to_legacy')); /* @phpstan-ignore-line */

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(TestModel2::class, $relation->getForeignModel());
        $this->assertEquals('id', $relation->getForeignKey());
        $this->assertEquals('belongs_to_legacy', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
    }

    public function testBelongsToMany(): void
    {
        $model = new RelationshipTester();
        $relation = Relationship::make($model, RelationshipTester::definition()->get('belongs_to_many')); /* @phpstan-ignore-line */

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertEquals(TestModel2::class, $relation->getForeignModel());
        $this->assertEquals('test_model2_id', $relation->getForeignKey());
        $this->assertEquals('relationship_tester_id', $relation->getLocalKey());
        $this->assertEquals($model, $relation->getLocalModel());
        $this->assertEquals('RelationshipTesterTestModel2', $relation->getTablename());
    }

    public function testPolymorphic(): void
    {
        $model = new RelationshipTester();
        $relation = Relationship::make($model, RelationshipTester::definition()->get('polymorphic')); /* @phpstan-ignore-line */

        $this->assertInstanceOf(Polymorphic::class, $relation);
        $this->assertEquals(['card' => Card::class, 'bank_account' => BankAccount::class], $relation->getModelMapping());
        $this->assertEquals('id', $relation->getForeignKey());
        $this->assertEquals('polymorphic_id', $relation->getLocalIdKey());
        $this->assertEquals('polymorphic_type', $relation->getLocalTypeKey());
        $this->assertEquals($model, $relation->getLocalModel());
    }
}
