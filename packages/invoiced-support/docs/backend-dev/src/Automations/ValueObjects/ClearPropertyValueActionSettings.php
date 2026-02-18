<?php

namespace App\Automations\ValueObjects;

use App\Core\Utils\Enums\ObjectType;

class ClearPropertyValueActionSettings extends AbstractActionObjectTypeSettings
{
    public function __construct(
        string $object_type,
        public string $name
    ) {
        parent::__construct($object_type);
    }

    public function validate(ObjectType $sourceObject): void
    {
        $fields = $this->getAvailableFields($this->object_type);
        $this->validateFieldWritable($this->name, $fields);
    }
}
