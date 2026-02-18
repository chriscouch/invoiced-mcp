<?php

namespace App\Reports\ReportBuilder;

use App\Companies\Models\Company;
use App\Core\Utils\ObjectConfiguration;
use App\Metadata\Libs\CustomFieldRepository;
use App\Metadata\Models\CustomField;
use Symfony\Component\Yaml\Yaml;

/**
 * Repository of report configuration data, including
 * available objects, fields and joins for each supported
 * data type. The configuration for reports can be found at:
 *   config/report_fields.yaml.
 */
final class ReportConfiguration
{
    private static ?self $instance = null;

    public function __construct(private array $objects)
    {
    }

    /**
     * Gets an instance of the report field configuration.
     */
    public static function get(bool $useCache = true): self
    {
        if (!self::$instance) {
            $objects = null;
            if ($useCache) {
                $phpCacheFile = dirname(dirname(dirname(__DIR__))).'/assets/report_fields.php';
                if (file_exists($phpCacheFile)) {
                    $objects = include_once $phpCacheFile;
                }
            }

            if (!is_array($objects)) {
                $objects = self::buildConfig();
            }

            self::$instance = new self($objects);
        }

        return self::$instance;
    }

    private static function buildConfig(): array
    {
        $yamlFile = dirname(dirname(dirname(__DIR__))).'/config/report_fields.yaml';
        $objects = Yaml::parseFile($yamlFile);

        // Overlay the object field data onto each object
        $objectConfiguration = ObjectConfiguration::get();
        foreach ($objects as $objectType => &$entry) {
            if (!isset($entry['fields'])) {
                $entry['fields'] = $objectConfiguration->getFields($objectType);
            }
        }

        return $objects;
    }

    /**
     * Gets the configuration for all objects.
     */
    public function all(): array
    {
        return $this->objects;
    }

    /**
     * Checks if an object is reportable.
     */
    public function hasObject(string $object): bool
    {
        return isset($this->objects[$object]) || 'metadata' == $object;
    }

    /**
     * Gets the configuration for an object.
     */
    public function getObject(string $object): array
    {
        if ('metadata' == $object) {
            return [];
        }

        return $this->objects[$object];
    }

    /**
     * Checks if an object has a field.
     */
    public function hasField(string $object, string $id): bool
    {
        // Make a special exception for metadata
        if ('metadata' == $object) {
            // The object can only have this metadata if the ID is valid
            return CustomField::validateID($id);
        }

        return isset($this->objects[$object]['fields'][$id]);
    }

    /**
     * Gets the field definition for an object. This assumes
     * that hasField() was already called to check the existence
     * of the field.
     */
    public function getField(string $object, string $id, ?string $metadataObject, Company $company): array
    {
        // Make a special exception for metadata
        if ('metadata' == $object) {
            // Attempt to get custom field name
            $customField = null;
            if ($metadataObject) {
                $repository = CustomFieldRepository::get($company);
                $customField = $repository->getCustomField($metadataObject, $id);
            }

            return [
                'name' => $customField ? $customField->name : $id,
                'type' => 'string',
            ];
        }

        return $this->objects[$object]['fields'][$id];
    }

    /**
     * Checks if an object supports a join to a different object.
     */
    public function hasJoin(string $parentObject, string $object): bool
    {
        return isset($this->objects[$parentObject]['fields'][$object]) && 'join' == $this->objects[$parentObject]['fields'][$object]['type'];
    }

    /**
     * Gets the join from one object to a different object. This assumes
     * that hasJoin() was already called to check the existence of the
     * join.
     */
    public function getJoin(string $parentObject, string $object): array
    {
        return $this->objects[$parentObject]['fields'][$object];
    }
}
