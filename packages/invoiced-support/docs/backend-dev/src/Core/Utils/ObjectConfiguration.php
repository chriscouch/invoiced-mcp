<?php

namespace App\Core\Utils;

use Symfony\Component\Yaml\Yaml;

/**
 * Repository of object configuration data that describes
 * the available fields on each object type. The configuration is at:
 *   config/object_fields.yaml.
 */
final class ObjectConfiguration
{
    private static ?self $instance = null;

    public function __construct(private array $objects)
    {
    }

    /**
     * Gets an instance of the object configuration.
     */
    public static function get(bool $useCache = true): self
    {
        if (!self::$instance) {
            $fields = null;
            if ($useCache) {
                $phpCacheFile = dirname(dirname(dirname(__DIR__))).'/assets/object_fields.php';
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
        $fields = [];
        $yamlDir = dirname(dirname(dirname(__DIR__))).'/config/objectFields';
        foreach ((array) glob($yamlDir.'/*.yaml') as $yamlFile) {
            $objectFields = Yaml::parseFile((string) $yamlFile);

            foreach ($objectFields['fields'] as &$field) {
                $field['writable'] = $field['writable'] ?? true;
                $field['filterable'] = $field['filterable'] ?? true;
            }

            $object = explode('.', basename((string) $yamlFile))[0];
            $fields[$object] = $objectFields;
        }

        return $fields;
    }

    /**
     * Gets the configuration for all objects.
     */
    public function all(): array
    {
        return $this->objects;
    }

    public function exists(string $type): bool
    {
        return isset($this->objects[$type]);
    }

    /**
     * Gets the fields for an object type.
     */
    public function getFields(string $type): array
    {
        return $this->objects[$type]['fields'];
    }
}
