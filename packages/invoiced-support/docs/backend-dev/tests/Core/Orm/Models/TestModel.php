<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Type;

class TestModel extends Model
{
    protected static array $properties = [];

    public static ?Query $query = null;

    protected function initialize(): void
    {
        self::$properties['test_hook'] = new Property(
            type: Type::STRING,
            null: true,
        );

        parent::initialize();
    }

    protected static function getProperties(): array
    {
        return array_replace([
            'relation' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: TestModel2::class,
            ),
            'answer' => new Property(
                type: Type::STRING,
            ),
            'mutator' => new Property(
                in_array: false,
            ),
            'accessor' => new Property(
                in_array: false,
            ),
            'encrypted' => new Property(
                encrypted: true,
            ),
            'appended' => new Property(
                persisted: false,
                in_array: true,
            ),
        ], self::$properties);
    }

    protected function getMassAssignmentAllowed(): ?array
    {
        return ['id', 'relation', 'answer', 'mutator', 'accessor', 'fail'];
    }

    public static function query(): Query
    {
        if ($query = self::$query) {
            self::$query = null;

            return $query;
        }

        return parent::query();
    }

    public static function setQuery(Query $query): void
    {
        self::$query = $query;
    }

    protected function setMutatorValue(mixed $value): mixed
    {
        return strtoupper($value);
    }

    protected function getAccessorValue(mixed $value): mixed
    {
        return strtolower($value);
    }

    protected function getAppendedValue(): bool
    {
        return true;
    }
}
