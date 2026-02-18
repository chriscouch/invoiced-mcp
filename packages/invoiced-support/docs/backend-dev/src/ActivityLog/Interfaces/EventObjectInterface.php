<?php

namespace App\ActivityLog\Interfaces;

use App\Core\Utils\Enums\ObjectType;

interface EventObjectInterface
{
    /**
     * Gets the type of this object that is stored on the event.
     */
    public function getEventObjectType(): ObjectType;

    /**
     * Gets the ID of this object that is stored on the event.
     */
    public function getEventObjectId(): string;

    /**
     * Gets the tenant ID from this object that is stored on the event.
     */
    public function getEventTenantId(): int;

    /**
     * Converts the object to the representation that is
     * stored on the event.
     */
    public function getEventObject(): array;

    /**
     * Gets the objects associated with this object when
     * creating events.
     */
    public function getEventAssociations(): array;
}
