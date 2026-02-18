<?php

namespace App\Core\Entitlements;

use App\Companies\Models\Company;
use App\Core\Entitlements\Models\Feature;
use Doctrine\DBAL\Connection;

/**
 * Checks if a feature flag is on.
 */
class FeatureChecker
{
    private array $cache = [];

    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Checks the presence of a feature flag.
     */
    public function check(string $feature, Company $company): bool
    {
        $key = ($company->id() ?: uniqid()).'_'.$feature;
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->_check($feature, $company);
        }

        return $this->cache[$key];
    }

    //
    // Helpers
    //

    private function _check(string $feature, Company $company): bool
    {
        // first check overrides
        $overrides = $this->getOverrides($company);
        if (isset($overrides[$feature])) {
            return $overrides[$feature];
        }

        // check if the product supports the feature
        return $this->hasFeatureFromProduct($company, $feature);
    }

    /**
     * Checks if a product has a feature.
     */
    private function hasFeatureFromProduct(Company $company, string $feature): bool
    {
        return in_array($feature, $this->getProductFeatures($company));
    }

    /**
     * Gets features from the database that belong to products installed in the company.
     */
    private function getProductFeatures(Company $company): array
    {
        $id = $company->id();
        if (!$id) {
            return [];
        }

        $key = $company->id().'_products';
        if (!isset($this->cache[$key])) {
            /** @var Connection $database */
            $database = Feature::getDriver()->getConnection(null);
            $this->cache[$key] = $database->fetchFirstColumn('SELECT f.feature FROM InstalledProducts i JOIN ProductFeatures f ON f.product_id=i.product_id WHERE i.tenant_id=?', [$id]);
        }

        return $this->cache[$key];
    }

    /**
     * Gets feature flag overrides in the database.
     */
    private function getOverrides(Company $company): array
    {
        $id = $company->id();
        if (!$id) {
            return [];
        }

        $key = $company->id().'_overrides';
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = [];
            $features = Feature::queryWithTenant($company)->first(1000);
            foreach ($features as $feature) {
                $featureKey = strtolower($feature->feature);
                $this->cache[$key][$featureKey] = $feature->enabled;
            }
        }

        return $this->cache[$key];
    }
}
