<?php

namespace App\Tests\Core\RestApi;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int|null   $author
 * @property string     $body
 * @property int|string $date
 */
class Post extends Model
{
    public static bool $without;

    protected static function getProperties(): array
    {
        return [
            'author' => new Property(
                type: Type::INTEGER,
                null: true,
                relation: 'Person',
            ),
            'body' => new Property(
                type: Type::STRING,
            ),
            'date' => new Property(
                type: Type::DATE_UNIX,
                in_array: false,
            ),
        ];
    }

    public function withoutArrayHook(): void
    {
        self::$without = true;
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['appended'] = $this->appended; /* @phpstan-ignore-line */

        return $result;
    }

    public function toArrayHook(array &$result, array $exclude, array $include, array $expand): void
    {
        if (!isset($exclude['hook'])) {
            $result['hook'] = true;
        }

        if (isset($include['include'])) {
            $result['include'] = true;
        }
    }

    protected function getPersonValue(): ?Person
    {
        return $this->relation('author');
    }
}
