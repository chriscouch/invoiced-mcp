<?php

namespace App\ActivityLog\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ModelNormalizer;

/**
 * This trait will implement common event object methods
 * for models.
 */
trait EventObjectTrait
{
    public function getEventObjectType(): ObjectType
    {
        return ObjectType::fromModel($this);
    }

    public function getEventObjectId(): string
    {
        if (isset($this->id)) {
            return (string) $this->id;
        }

        return (string) $this->id();
    }

    public function getEventTenantId(): int
    {
        return $this->tenant_id;
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this);
    }

    public function getEventAssociations(): array
    {
        return [];
    }
}
