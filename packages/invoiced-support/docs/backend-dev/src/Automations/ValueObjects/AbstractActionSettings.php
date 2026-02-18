<?php

namespace App\Automations\ValueObjects;

use App\Automations\AutomationConfiguration;
use App\Automations\Exception\AutomationException;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;

abstract class AbstractActionSettings
{
    /**
     * @throws AutomationException
     */
    public function validate(ObjectType $sourceObject): void
    {
    }

    public function serialize(): object
    {
        return (object) (array) $this;
    }

    public function getAvailableFields(string $subject): array
    {
        return AutomationConfiguration::get()->getFields($subject);
    }

    protected function validateFieldWritable(string $fieldId, array $fields): void
    {
        if (str_starts_with($fieldId, 'metadata.')) {
            return;
        }
        if ('company' === $fieldId || !isset($fields[$fieldId]) || (!$fields[$fieldId]['writable'] && 'join' !== $fields[$fieldId]['type'])) {
            throw new AutomationException('Field `'.$fieldId.'` is not writable');
        }
    }

    public function getRelatedObject(AutomationContext $context): ?MultitenantModel
    {
        $object = $context->sourceObject;
        if (!$object || !isset($this->object_type)) {
            return $object;
        }
        $modelClass = ObjectType::fromTypeName($this->object_type)->modelClass();
        $model = $object instanceof $modelClass ? $object : $object->relation($this->object_type);
        if (!$model instanceof MultitenantModel) {
            return null;
        }

        return $model;
    }
}
