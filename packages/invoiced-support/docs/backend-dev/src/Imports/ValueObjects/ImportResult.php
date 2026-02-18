<?php

namespace App\Imports\ValueObjects;

/**
 * Represents the result returned from an importer run.
 */
class ImportResult
{
    /**
     * @param \App\Imports\Models\ImportedObject[] $objects
     */
    public function __construct(private int $numCreated, private int $numUpdated, private int $numFailed, private array $failures, private array $objects)
    {
    }

    public function getNumCreated(): int
    {
        return $this->numCreated;
    }

    public function getNumUpdated(): int
    {
        return $this->numUpdated;
    }

    public function getNumFailed(): int
    {
        return $this->numFailed;
    }

    public function getFailures(): array
    {
        return $this->failures;
    }

    public function getObjects(): array
    {
        return $this->objects;
    }
}
