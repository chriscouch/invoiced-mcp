<?php

namespace App\Core\Orm\Relation;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

/**
 * Pivot model shim for use by relationships.
 */
final class Pivot extends Model
{
    protected static array $properties = [];
    private string $tablename;

    public function setTablename(string $tablename): void
    {
        $this->tablename = $tablename;
    }

    public function getTablename(): string
    {
        return $this->tablename;
    }

    public function setProperties(string $localKey, string $foreignKey): void
    {
        self::$properties = [
            $localKey => new Property(),
            $foreignKey => new Property(),
        ];
        $this->initialize();
    }

    protected static function getProperties(): array
    {
        return self::$properties;
    }
}
