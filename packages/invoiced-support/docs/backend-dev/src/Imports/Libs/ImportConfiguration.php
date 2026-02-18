<?php

namespace App\Imports\Libs;

use App\Core\Utils\ObjectConfiguration;
use Symfony\Component\Yaml\Yaml;

/**
 * Repository of import configuration data, including
 * importable objects and fields. The configuration for
 * automations can be found at:
 *   config/import_fields.yaml.
 */
final class ImportConfiguration
{
    private static ?self $instance = null;

    public function __construct(private array $imports)
    {
    }

    /**
     * Gets an instance of the import field configuration.
     */
    public static function get(bool $useCache = true): self
    {
        if (!self::$instance) {
            $fields = null;
            if ($useCache) {
                $phpCacheFile = dirname(dirname(dirname(__DIR__))).'/assets/import_fields.php';
                if (file_exists($phpCacheFile)) {
                    $fields = include_once $phpCacheFile;
                }
            }

            if (!is_array($fields)) {
                $fields = self::buildConfig();
            }

            self::$instance = new self($fields);
        }

        return self::$instance;
    }

    private static function buildConfig(): array
    {
        $yamlFile = dirname(dirname(dirname(__DIR__))).'/config/import_fields.yaml';
        $imports = Yaml::parseFile($yamlFile);

        // Overlay the object field data onto each object
        $objectConfiguration = ObjectConfiguration::get();
        foreach ($imports as $objectType => &$entry) {
            // grab field definition from object configuration
            if (!isset($entry['fields'])) {
                $entry['fields'] = [];
                foreach ($objectConfiguration->getFields($objectType) as $fieldId => $field) {
                    if ($field['writable'] && 'metadata' != $field['type'] && 'join' != $field['type']) {
                        $entry['fields'][$fieldId] = $field;
                    }
                }
            }

            // add import only fields
            if (isset($entry['added_fields'])) {
                $entry['fields'] = array_merge($entry['fields'], $entry['added_fields']);
            }

            // add field aliases
            if (isset($entry['aliases'])) {
                foreach ($entry['aliases'] as $fieldId => $aliases) {
                    $entry['fields'][$fieldId]['aliases'] = $aliases;
                }
            }
        }

        return $imports;
    }

    /**
     * Gets the configuration for all imports.
     */
    public function all(): array
    {
        return $this->imports;
    }

    /**
     * Gets the name for an import type.
     */
    public function getName(string $type): string
    {
        return $this->imports[$type]['name'];
    }

    /**
     * Gets the fields for an import type.
     */
    public function getFields(string $type): array
    {
        return $this->imports[$type]['fields'] ?? [];
    }

    /**
     * Returns a list of fields in the imported data which belong to the customer.
     * The return result is a key-value array where the keys are the name of the
     * field in the import and the value is the name of the field on the customer object.
     *
     * If null is returned then that implies the imported data does not have an
     * associated customer.
     *
     * The default behavior is that imported data does NOT have a customer.
     */
    public function getCustomerFields(string $type): ?array
    {
        return $this->imports[$type]['customerFieldMapping'] ?? null;
    }

    /**
     * Returns a list of supported operations for the data type. If
     * a list of supported operations is not given then the default
     * allowed operation is create only.
     */
    public function getSupportedOperations(string $type): ?array
    {
        return $this->imports[$type]['supportedOperations'] ?? ['create'];
    }
}
