<?php

namespace App\Metadata\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Interfaces\MetadataStorageInterface;
use App\Metadata\Storage\AttributeStorage;
use App\Metadata\Libs\AttributeStorageFacade;
use App\Metadata\Libs\LegacyStorageFacade;
use App\Metadata\Libs\MetadataQuery;
use App\Metadata\Storage\LegacyMetadataStorage;
use App\Metadata\Libs\MetadataListener;
use App\Core\Orm\Query;
use stdClass;

trait MetadataTrait
{
    private ?object $persistedMetadata = null;
    private ?object $unsavedMetadata = null;

    /**
     * Initializes the metadata listener on this model.
     */
    protected function autoInitializeMetadata(): void
    {
        // install the metadata listener for this model
        MetadataListener::add($this);
    }

    //
    // MetadataModelInterface
    //

    public function getMetadataWriters(): array
    {
        $storage = [];

        if ($this->writeToLegacyMetadataStorage()) {
            $storage[] = $this->getLegacyStorage();
        }

        if ($this->writeToAttributeStorage()) {
            $storage[] = $this->getAttributeStorage();
        }

        return $storage;
    }

    public function getMetadataReader(): MetadataStorageInterface
    {
        return $this->readFromAttributeStorage() ? $this->getAttributeStorage() : $this->getLegacyStorage();
    }

    public function getMetadataTablePrefix(): string
    {
        return static::modelName();
    }

    public function getMetadataToBeSaved(): ?object
    {
        return $this->unsavedMetadata;
    }

    public function hydrateMetadata(object $metadata): void
    {
        $this->persistedMetadata = $metadata;
        $this->unsavedMetadata = null;
    }

    public static function queryMetadata($condition): Query
    {
        if (!is_array($condition)) {
            $condition = [$condition];
        }

        $query = static::query();
        MetadataQuery::addTo($query, $condition);

        return $query;
    }

    //
    // Accessors / Mutators
    //

    /**
     * Gets the `metadata` property.
     */
    protected function getMetadataValue(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        return $this->getPersistedMetadata();
    }

    public function getPersistedMetadata(): object
    {
        if (!is_object($this->persistedMetadata)) {
            // look up metadata from data stores if the model is persisted
            if ($id = $this->id()) {
                $this->persistedMetadata = $this->getMetadataReader()->retrieve($this);
            } else {
                $this->persistedMetadata = new stdClass();
            }
        }

        return $this->persistedMetadata;
    }

    /**
     * Sets the `metadata` property.
     *
     * @param object $metadata
     */
    protected function setMetadataValue($metadata): object
    {
        // metadata can only be an object
        $metadata = (object) $metadata;
        $this->unsavedMetadata = $metadata;

        return $metadata;
    }

    //
    // Helpers
    //

    /**
     * When this function returns true, it instructs
     * the application to write metadata to the legacy store.
     */
    protected function writeToLegacyMetadataStorage(): bool
    {
        return true;
    }

    protected function writeToAttributeStorage(): bool
    {
        return false;
    }

    protected function readFromAttributeStorage(): bool
    {
        return false;
    }

    private function getAttributeStorage(): AttributeStorage
    {
        return AttributeStorageFacade::get();
    }

    private function getLegacyStorage(): LegacyMetadataStorage
    {
        return LegacyStorageFacade::get();
    }

    public function getObjectName(): string
    {
        return ObjectType::fromModel($this)->typeName();
    }
}
