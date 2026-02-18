<?php

namespace App\Metadata\Interfaces;

use App\Metadata\Exception\MetadataStorageException;
use App\Metadata\ValueObjects\MetadataQueryCondition;

interface MetadataStorageInterface
{
    /**
     * Persists the metadata for a model.
     *
     * @throws MetadataStorageException
     */
    public function save(MetadataModelInterface $model, object $metadata, bool $isUpdate): void;

    /**
     * Retrieves the metadata for a model from the data layer.
     */
    public function retrieve(MetadataModelInterface $model): object;

    /**
     * Deletes all metadata associated with this model.
     */
    public function delete(MetadataModelInterface $model): void;

    /**
     * Adds metadata where conditions to a model query.
     *
     * @param MetadataQueryCondition[] $conditions
     *
     * @throws MetadataStorageException
     */
    public function buildSqlConditions(array $conditions, MetadataModelInterface $model, int $tenantId, ?string $idColumn = null): array;
}
