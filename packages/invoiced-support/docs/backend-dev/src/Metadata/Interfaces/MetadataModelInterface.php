<?php

namespace App\Metadata\Interfaces;

use App\Metadata\ValueObjects\MetadataQueryCondition;
use App\Core\Orm\Model;
use App\Core\Orm\Query;

/**
 * @property object $metadata
 *
 * @phpstan-require-extends Model
 */
interface MetadataModelInterface
{
    /**
     * Gets the data stores where metadata should be
     * written to.
     *
     * @return MetadataStorageInterface[]
     */
    public function getMetadataWriters(): array;

    /**
     * Gets the data store responsible for retrieving metadata
     * for a model.
     */
    public function getMetadataReader(): MetadataStorageInterface;

    /**
     * Gets the prefix of the attribute tables. For example,
     * the Invoice model would return Invoice to use the
     * `InvoiceAttributes` and `InvoiceValues` tables.
     */
    public function getMetadataTablePrefix(): string;

    /**
     * Gets the metadata stored in the model's memory that
     * needs to be persisted to the data layer.
     */
    public function getMetadataToBeSaved(): ?object;

    /**
     * Hyrdates metadata within the model's in-memory data.
     */
    public function hydrateMetadata(object $metadata): void;

    /**
     * Creates a metadata query.
     *
     * @param MetadataQueryCondition[]|MetadataQueryCondition $condition
     */
    public static function queryMetadata($condition): Query;

    /**
     * Gets Persisted metadata.
     */
    public function getPersistedMetadata(): object;

    /**
     * Gets the model ID.
     *
     * @return string|number|false ID
     */
    public function id();

    public function getObjectName(): string;

    /**
     * @return string - table name
     */
    public function getTablename(): string;
}
