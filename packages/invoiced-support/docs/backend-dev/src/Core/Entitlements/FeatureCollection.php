<?php

namespace App\Core\Entitlements;

use App\Companies\Models\Company;
use App\Core\Entitlements\Models\Feature;
use App\Core\Entitlements\Models\InstalledProduct;
use App\Core\Entitlements\Models\Product;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use JsonSerializable;
use App\Core\Orm\Exception\ModelException;

class FeatureCollection implements JsonSerializable
{
    private static FeatureChecker $checker;

    public function __construct(private Company $company)
    {
    }

    /**
     * Checks if an account has a feature.
     */
    public function has(string $feature): bool
    {
        if (!isset(self::$checker)) {
            self::buildChecker();
        }

        return self::$checker->check($feature, $this->company);
    }

    /**
     * Enables a feature flag for this company.
     *
     * @throws ModelException
     */
    public function enable(string $name): void
    {
        $this->setValue($name, true);
    }

    /**
     * Disables a feature flag for this company.
     *
     * @throws ModelException
     */
    public function disable(string $name): void
    {
        $this->setValue($name, false);
    }

    /**
     * Removes a feature flag for this company, if there is one.
     *
     * @throws ModelException
     */
    public function remove(string $name): void
    {
        $flag = Feature::queryWithTenant($this->company)
            ->where('feature', $name)
            ->oneOrNull();
        if ($flag instanceof Feature) {
            $flag->deleteOrFail();
            self::clearCache();
        }
    }

    /**
     * Creates an installed product for this company. This does not
     * perform product installation tasks, like configuration or data creation.
     *
     * @throws ModelException
     */
    public function enableProduct(Product $product): void
    {
        $installedProduct = InstalledProduct::queryWithTenant($this->company)
            ->where('product_id', $product)
            ->oneOrNull();
        if (!$installedProduct) {
            $installedProduct = new InstalledProduct();
            $installedProduct->tenant_id = $this->company->id;
            $installedProduct->product = $product;
            $installedProduct->installed_on = CarbonImmutable::now();
            $installedProduct->saveOrFail();
        }

        self::clearCache();
    }

    /**
     * Deletes an installed product for this company.
     *
     * @throws ModelException
     */
    public function disableProduct(Product $product): void
    {
        $installedProduct = InstalledProduct::queryWithTenant($this->company)
            ->where('product_id', $product)
            ->oneOrNull();
        if ($installedProduct) {
            $installedProduct->deleteOrFail();
        }

        self::clearCache();
    }

    /**
     * Gets all the features for an account.
     *
     * @return string[]
     */
    public function all(): array
    {
        $features = [];
        foreach (FeatureData::get()->all() as $feature) {
            if ($this->has($feature)) {
                $features[] = $feature;
            }
        }

        // Remove autopay flag if not supported
        // This is kept for BC with the dashboard
        // Intentionally not added to individual feature checking
        $key = array_search('autopay', $features);
        if (false !== $key) {
            if (!PaymentMethod::acceptsAutoPay($this->company)) {
                unset($features[$key]);
            }
        }

        sort($features);

        return $features;
    }

    /**
     * Gets the list of products installed for a company.
     *
     * @return string[]
     */
    public function allProducts(): array
    {
        /** @var Connection $database */
        $database = Product::getDriver()->getConnection(null);

        return $database->fetchFirstColumn(
            'SELECT p.name FROM InstalledProducts i JOIN Products p ON p.id=i.product_id WHERE i.tenant_id=? ORDER BY p.name',
            [$this->company->id]
        );
    }

    /**
     * Sets the value of a feature flag for this company.
     *
     * @throws ModelException
     */
    private function setValue(string $feature, bool $enabled): void
    {
        $flag = Feature::queryWithTenant($this->company)
            ->where('feature', $feature)
            ->oneOrNull();
        if ($flag instanceof Feature && $flag->enabled == $enabled) {
            return;
        }

        if (!$flag) {
            $flag = new Feature();
            $flag->feature = $feature;
            $flag->tenant_id = (int) $this->company->id();
        }

        $flag->enabled = $enabled;
        $flag->saveOrFail();

        self::clearCache();
    }

    public function jsonSerialize(): array
    {
        return $this->all();
    }

    /**
     * Builds the feature checker instance.
     */
    private static function buildChecker(): void
    {
        self::$checker = new FeatureChecker();
    }

    /**
     * Clears the feature checker cache.
     */
    public static function clearCache(): void
    {
        if (isset(self::$checker)) {
            self::$checker->clearCache();
        }
    }
}
