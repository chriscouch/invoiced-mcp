<?php

namespace App\Imports\ValueObjects;

use App\Core\Orm\Model;

/**
 * @template T of Model
 */
final class ImportRecordResult
{
    const CREATE = 'create';
    const UPDATE = 'update';
    const DELETE = 'delete';
    const VOID = 'void';

    private bool $created;
    private bool $updated;
    private bool $deleted;

    public function __construct(private ?Model $model = null, private ?string $action = null)
    {
        $this->created = self::CREATE == $action;
        $this->updated = self::UPDATE == $action;
        $this->deleted = self::DELETE == $action || self::VOID == $action;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function wasCreated(): bool
    {
        return $this->created;
    }

    public function wasUpdated(): bool
    {
        return $this->updated;
    }

    /**
     * Returns true if the model was deleted OR voided.
     */
    public function wasDeleted(): bool
    {
        return $this->deleted;
    }

    public function hadChange(): bool
    {
        return $this->created || $this->updated || $this->deleted;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }
}
