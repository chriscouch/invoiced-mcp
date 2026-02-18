<?php

namespace App\Core\Search\Interfaces;

use SplFixedArray;

/**
 * This is the contract for a search strategy index.
 * An index represents a logical index for a combination
 * of company and object type. It is not a 1:1 mapping with
 * the physical search indexes, of which each search backend
 * can have a different indexing strategy.
 *
 * For example, all invoices might be stored in a single index
 * across the entire service and therefore an index in the application
 * layer represents a subset of the physical index.
 */
interface IndexInterface
{
    /**
     * Gets the name of the index.
     */
    public function getName(): string;

    /**
     * Inserts a document into the index.
     */
    public function insertDocument(string $id, array $document, array $parameters = []): void;

    /**
     * Updates a document in the index.
     */
    public function updateDocument(string $id, array $document, array $parameters = []): void;

    /**
     * Deletes a document from the index.
     */
    public function deleteDocument(string $id): void;

    /**
     * Clears any spooled indexing operations.
     */
    public function clearSpool(): void;

    /**
     * Flushes any spooled indexing operations.
     */
    public function flushSpool(): void;

    /**
     * Checks if an index actually exists within the search backend.
     */
    public function exists(): bool;

    /**
     * Renames the index.
     */
    public function rename(string $newName): void;

    /**
     * Deletes the index.
     */
    public function delete(): void;

    /**
     * Gets a sorted list of all object IDs in the index.
     */
    public function getIds(): SplFixedArray;
}
