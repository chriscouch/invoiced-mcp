<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

class TestModel2 extends Model
{
    use AutoTimestamps;

    public static ?Query $query = null;

    public static function getIDProperties(): array
    {
        return ['id', 'id2'];
    }

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                type: Type::INTEGER,
            ),
            'id2' => new Property(
                type: Type::INTEGER,
            ),
            'default' => new Property(
                default: 'some default value',
            ),
            'validate' => new Property(
                null: true,
                validate: ['email', ['string', 'min' => 5]],
            ),
            'validate2' => new Property(
                null: true,
                validate: ['callable', 'fn' => function (&$value, array $options, Model $model) {
                    $model->getErrors()->add('Custom error message from callable', ['field' => $options['field']]);

                    return false;
                }, 'field' => 'validate2'],
                in_array: false,
            ),
            'unique' => new Property(
                validate: ['unique', 'column' => 'unique'],
            ),
            'required' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'hidden' => new Property(
                type: Type::BOOLEAN,
                default: false,
                in_array: false,
            ),
            'person' => new Property(
                type: Type::INTEGER,
                default: 20,
                in_array: false,
                relation: Person::class,
            ),
            'array' => new Property(
                type: Type::ARRAY,
                default: [
                    'tax' => '%',
                    'discounts' => false,
                    'shipping' => false,
                ],
                in_array: false,
            ),
            'object' => new Property(
                type: Type::OBJECT,
                in_array: false,
            ),
            'mutable_create_only' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                in_array: false,
            ),
            'protected' => new Property(),
        ];
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

    protected function getMassAssignmentProtected(): ?array
    {
        return ['protected'];
    }
}
