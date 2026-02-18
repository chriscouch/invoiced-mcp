<?php

namespace App\Automations;

use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ObjectConfiguration;
use App\Core\Orm\Definition;
use Symfony\Component\Yaml\Yaml;

/**
 * Repository of automation configuration data, including
 * supported objects and fields. The configuration for
 * automations can be found at:
 *   config/automation_fields.yaml.
 */
final class AutomationConfiguration
{
    private static ?self $instance = null;

    public function __construct(private array $automations)
    {
    }

    /**
     * Gets an instance of the automation field configuration.
     */
    public static function get(bool $useCache = true): self
    {
        if (!self::$instance) {
            $automations = null;
            if ($useCache) {
                $phpCacheFile = dirname(dirname(dirname(__DIR__))).'/assets/automation_fields.php';
                if (file_exists($phpCacheFile)) {
                    $automations = include_once $phpCacheFile;
                }
            }

            if (!is_array($automations)) {
                $automations = self::buildConfig();
            }

            self::$instance = new self($automations);
        }

        return self::$instance;
    }

    private static function buildConfig(): array
    {
        $yamlFile = dirname(dirname(__DIR__)).'/config/automation_fields.yaml';
        $automations = Yaml::parseFile($yamlFile);

        $definition = [];

        // Overlay the object field data onto each object
        $objectConfiguration = ObjectConfiguration::get();
        foreach ($automations as $objectType => &$entry) {
            $definition[$objectType] = ObjectType::fromTypeName($objectType)->modelClass()::definition();
            if (!isset($entry['fields'])) {
                $fieldCandidates = array_replace_recursive($objectConfiguration->getFields($objectType), $entry['fieldOverrides'] ?? []);
                $fieldCandidates = array_filter($fieldCandidates, fn ($value) => !isset($value['writable']) || $value['writable'] || 'join' !== $value['type']);
                $entry['fields'] = [];
                foreach ($fieldCandidates as $key => $fieldResult) {
                    if (($fieldResult['display'] ?? null) === false) {
                        unset($entry['fields'][$key]);
                        continue;
                    }
                    $def = $definition[$objectType]->get($key);
                    if (!isset($fieldResult['required'])) {
                        $fieldResult['required'] = $def?->required ?? false;
                    }
                    if (!isset($fieldResult['hasDefault'])) {
                        $fieldResult['hasDefault'] = $def?->hasDefault ?? false;
                    }
                    $entry['fields'][$key] = $fieldResult;
                }
            }
            uasort($entry['fields'], fn ($a, $b) => $a['name'] <=> $b['name']);
            unset($entry['fieldOverrides']);
        }

        // calculate automation actions
        foreach ($automations as $objectType => $data) {
            if (isset($data['subjectActions'])) {
                foreach ($data['subjectActions'] as $action => $actionObjects) {
                    $resultActions = [];
                    foreach ($actionObjects as $actionObject) {
                        $resultActions[$actionObject] = self::calculateRequiredFieldValues($automations, $definition, $actionObject, $objectType);
                    }
                    $automations[$objectType]['subjectActions'][$action] = $resultActions;
                }
            }
        }

        return $automations;
    }

    /**
     * @param Definition[] $definition
     */
    private static function calculateRequiredFieldValues(array $automations, array $definition, string $actionObject, string $objectType): array
    {
        $resultActionObject = [];
        foreach ($automations[$actionObject]['fields'] as $key => $field) {
            if (!isset($field['required']) || !$field['required']) {
                continue;
            }
            if ('join' === $field['type']) {
                if ($objectType === $actionObject) {
                    $resultActionObject[$key] = $key;
                    continue;
                }
                if ($actionObjectField = $definition[$actionObject]->get($key)) {
                    if ($relation = $actionObjectField->relation) {
                        if (ObjectType::fromModelClass($relation)->typeName() === $objectType) {
                            $resultActionObject[$actionObjectField->name] = $actionObjectField->foreign_key;
                            continue;
                        }
                        $fieldDefinition = $definition[$objectType]->all();
                        foreach ($fieldDefinition as $objectTypeField) {
                            if ($relation === $objectTypeField->relation) {
                                $resultActionObject[$actionObjectField->name] = $objectTypeField->name;
                                continue 2;
                            }
                        }
                    }
                }
            }
            if (isset($field['hasDefault']) && $field['hasDefault']) {
                continue;
            }
            $resultActionObject[$key] = null;
        }

        return $resultActionObject;
    }

    /**
     * Gets the configuration for all object types.
     */
    public function all(): array
    {
        return $this->automations;
    }

    /**
     * Gets the fields for an object type.
     */
    public function getFields(string $type): array
    {
        return $this->automations[$type]['fields'];
    }
}
