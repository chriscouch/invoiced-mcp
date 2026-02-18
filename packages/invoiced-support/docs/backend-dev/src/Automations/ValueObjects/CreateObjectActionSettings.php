<?php

namespace App\Automations\ValueObjects;

use App\Automations\AutomationConfiguration;
use App\Automations\Exception\AutomationException;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Orm\Validator;

class CreateObjectActionSettings extends AbstractActionObjectTypeSettings
{
    /** @var object[] */
    public array $fields;
    private array $availableFields;

    public function __construct(
        string $object_type,
        array $fields
    ) {
        parent::__construct($object_type);
        $this->fields = array_map(fn ($field) => (object) [
            'name' => $field->name,
            'value' => $field->value,
        ], $fields);
        $this->availableFields = $this->getAvailableFields($this->object_type);
    }

    public function getPreSaveFields(): array
    {
        return array_filter($this->fields, fn ($item) => !isset($this->availableFields[$item->name]['postSetRelation']));
    }

    public function getPostSaveFields(): array
    {
        return array_filter($this->fields, fn ($item) => isset($this->availableFields[$item->name]['postSetRelation']));
    }

    public function validate(ObjectType $sourceObject): void
    {
        $fieldsTo = [];
        foreach ($this->fields as $field) {
            if (!isset($field->name) || !isset($field->value)) {
                throw new AutomationException('Missing mapping for fields');
            }
            $this->validateFieldWritable($field->name, $this->availableFields);
            $fieldsTo[$field->name] = $field->value;
        }

        $configuration = AutomationConfiguration::get();
        $fields = $configuration->getFields($this->object_type);
        $modelClass = ObjectType::fromTypeName($this->object_type)->modelClass();
        $object = new $modelClass();
        $properties = $modelClass::definition()->all();
        foreach ($properties as $property) {
            // property is either not required or set by default for the new object or field is not writable
            if (!$property->required || $object->{$property->name} || !isset($fields[$property->name]) || (!$fields[$property->name]['writable'] && 'join' !== $fields[$property->name]['type'])) {
                continue;
            }
            if (!isset($fieldsTo[$property->name])) {
                throw new AutomationException('Missing required field mapping: '.$property->name);
            }
            $value = $fieldsTo[$property->name];
            // we don't validate variable
            if (str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
                return;
            }

            if ($property->relation && !(is_numeric($fieldsTo[$property->name]) || json_decode($value))) {
                throw new AutomationException('Invalid value for relation field: '.$property->name);
            }
            Validator::validateProperty($object, $property, $value);
        }
    }
}
