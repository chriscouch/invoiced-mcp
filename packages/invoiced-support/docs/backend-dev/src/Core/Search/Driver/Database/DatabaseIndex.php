<?php

namespace App\Core\Search\Driver\Database;

use App\Core\Search\Interfaces\IndexInterface;
use SplFixedArray;

/**
 * Since there is no separate index to maintain, the
 * indexing operations do nothing.
 */
class DatabaseIndex implements IndexInterface
{
    public function __construct(private string $modelClass)
    {
    }

    public function getName(): string
    {
        return $this->modelClass;
    }

    public function insertDocument(string $id, array $document, array $parameters = []): void
    {
    }

    public function updateDocument(string $id, array $document, array $parameters = []): void
    {
    }

    public function deleteDocument(string $id): void
    {
    }

    public function exists(): bool
    {
        return false;
    }

    public function rename(string $newName): void
    {
    }

    public function delete(): void
    {
    }

    public function getIds(): SplFixedArray
    {
        return new SplFixedArray();
    }

    public function clearSpool(): void
    {
    }

    public function flushSpool(): void
    {
    }
}
