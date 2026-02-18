<?php

namespace App\Automations\ValueObjects;

abstract class AbstractActionObjectTypeSettings extends AbstractActionSettings
{
    public function __construct(
        public string $object_type
    ) {
    }

    public function getSubjectFields(): array
    {
        return $this->getAvailableFields($this->object_type);
    }
}
