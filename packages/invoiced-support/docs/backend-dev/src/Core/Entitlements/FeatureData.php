<?php

namespace App\Core\Entitlements;

use Symfony\Component\Yaml\Yaml;

class FeatureData
{
    private static ?self $instance = null;

    public static function get(bool $useCache = true): self
    {
        if (!self::$instance) {
            $features = null;
            if ($useCache) {
                $phpCacheFile = dirname(dirname(dirname(__DIR__))).'/assets/features.php';
                if (file_exists($phpCacheFile)) {
                    $features = include_once $phpCacheFile;
                }
            }

            if (!is_array($features)) {
                $yamlFile = dirname(dirname(dirname(__DIR__))).'/config/features.yaml';
                $features = Yaml::parseFile($yamlFile);
            }

            self::$instance = new self($features);
        }

        return self::$instance;
    }

    public function __construct(private array $data)
    {
    }

    public function all(): array
    {
        return $this->data['frontend_feature_flags'];
    }

    public function getData(): array
    {
        return $this->data;
    }
}
