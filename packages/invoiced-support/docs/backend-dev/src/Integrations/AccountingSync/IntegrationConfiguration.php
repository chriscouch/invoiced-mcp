<?php

namespace App\Integrations\AccountingSync;

use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\AccountingSync\ValueObjects\TransformField;
use App\Integrations\Enums\IntegrationType;
use Symfony\Component\Yaml\Yaml;

/**
 * Repository of integration configuration data. The configuration is at:
 *   config/integrations
 */
final class IntegrationConfiguration
{
    private static ?self $instance = null;

    public function __construct(private readonly array $integrations)
    {
    }

    /**
     * Gets an instance of the integration configuration.
     */
    public static function get(bool $useCache = true): self
    {
        if (!self::$instance) {
            $integrations = null;
            if ($useCache) {
                $phpCacheFile = dirname(dirname(dirname(__DIR__))).'/assets/integrations.php';
                if (file_exists($phpCacheFile)) {
                    $integrations = include_once $phpCacheFile;
                }
            }

            if (!is_array($integrations)) {
                $integrations = self::buildConfig();
            }

            self::$instance = new self($integrations);
        }

        return self::$instance;
    }

    private static function buildConfig(): array
    {
        $integrations = [];
        $yamlDir = dirname(dirname(dirname(__DIR__))).'/config/integrations';
        foreach ((array) glob($yamlDir.'/*') as $dir) {
            $integrationName = basename((string) $dir);
            $integrations[$integrationName] = [];
            foreach ((array) glob($dir.'/*') as $yamlFile) {
                $dataFlow = explode('.', basename((string) $yamlFile))[0];
                $integrations[$integrationName][$dataFlow] = Yaml::parseFile((string) $yamlFile);
            }
        }

        return $integrations;
    }

    /**
     * Gets the configuration for all integrations.
     */
    public function all(): array
    {
        return $this->integrations;
    }

    public function exists(IntegrationType $integration, string $dataFlow): bool
    {
        return isset($this->integrations[$integration->toString()][$dataFlow]);
    }

    /**
     * Gets the mapping for an integration data flow.
     *
     * @return TransformField[]
     */
    public function getMapping(IntegrationType $integration, string $dataFlow): array
    {
        $config = $this->integrations[$integration->toString()][$dataFlow]['mapping'];

        $mapping = [];
        foreach ($config as $value) {
            if (isset($value['type'])) {
                $value['type'] = TransformFieldType::from($value['type']);
            }

            $mapping[] = new TransformField(...$value);
        }

        return $mapping;
    }
}
