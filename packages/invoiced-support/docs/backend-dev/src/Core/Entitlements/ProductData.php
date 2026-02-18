<?php

namespace App\Core\Entitlements;

use Symfony\Component\Yaml\Yaml;

class ProductData
{
    private static ?self $instance = null;

    public static function get(bool $useCache = true): self
    {
        if (!self::$instance) {
            $products = null;
            if ($useCache) {
                $phpCacheFile = dirname(dirname(dirname(__DIR__))).'/assets/products.php';
                if (file_exists($phpCacheFile)) {
                    $products = include_once $phpCacheFile;
                }
            }

            if (!is_array($products)) {
                $yamlFile = dirname(dirname(dirname(__DIR__))).'/config/products.yaml';
                $products = Yaml::parseFile($yamlFile);
            }

            self::$instance = new self($products);
        }

        return self::$instance;
    }

    public function __construct(private array $data)
    {
    }

    public function getData(): array
    {
        return $this->data;
    }
}
