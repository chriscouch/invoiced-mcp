<?php

namespace App\Core\Orm\Traits;

use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Type;
use App\Core\Orm\Validator;

/**
 * Allows models to be soft deleted.
 *
 * @property bool     $deleted
 * @property int|null $deleted_at
 */
trait SoftDelete
{
    protected static function autoDefinitionSoftDelete(): array
    {
        return [
            'deleted' => new Property(
                type: Type::BOOLEAN,
            ),
            'deleted_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
                validate: 'timestamp|db_timestamp',
            ),
        ];
    }

    protected function performDelete(): bool
    {
        $updateArray = [
            'deleted' => true,
            'deleted_at' => time(),
        ];
        foreach ($updateArray as $k => &$v) {
            /** @var Property $property */
            $property = static::definition()->get($k);
            Validator::validateProperty($this, $property, $v);
        }

        $updated = static::getDriver()->updateModel($this, $updateArray);
        if ($updated) {
            $this->_values['deleted'] = true;
            $this->_values['deleted_at'] = time();
        }

        return $updated;
    }

    /**
     * Restores a soft-deleted model.
     */
    public function restore(): bool
    {
        if (!$this->deleted) {
            throw new ModelException('restore() can only be called on a deleted model');
        }

        $this->deleted = false;
        $this->deleted_at = null;

        return $this->save();
    }

    /**
     * Generates a new query instance that excludes soft-deleted models.
     */
    public static function withoutDeleted(): Query
    {
        return static::query()->where('deleted', false);
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }
}
