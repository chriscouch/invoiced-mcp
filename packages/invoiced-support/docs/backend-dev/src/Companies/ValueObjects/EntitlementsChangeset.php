<?php

namespace App\Companies\ValueObjects;

use App\Core\Billing\Enums\BillingInterval;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;
use JsonSerializable;

class EntitlementsChangeset implements JsonSerializable
{
    /**
     * @param Product[]            $products
     * @param array<int, array>    $productPrices
     * @param array<string, bool>  $features
     * @param array<string, int>   $quota
     * @param array<string, array> $usagePricing
     */
    public function __construct(
        public readonly array $products = [],
        public readonly array $productPrices = [],
        public readonly bool $replaceExistingProducts = false,
        public readonly array $features = [],
        public readonly array $quota = [],
        public readonly array $usagePricing = [],
        public readonly BillingInterval|null $billingInterval = null,
    ) {
    }

    public function withFeatures(array $features): self
    {
        return new EntitlementsChangeset(
            products: $this->products,
            productPrices: $this->productPrices,
            replaceExistingProducts: $this->replaceExistingProducts,
            features: array_replace($this->features, $features),
            quota: $this->quota,
            usagePricing: $this->usagePricing,
            billingInterval: $this->billingInterval,
        );
    }

    public function getProductPrice(Product $product): ?array
    {
        return $this->productPrices[$product->id] ?? null;
    }

    public function jsonSerialize(): array
    {
        $products = [];
        foreach ($this->products as $product) {
            $products[] = $product->id;
        }

        $productPrices = [];
        foreach ($this->productPrices as $productId => $productPrice) {
            $productPrices[$productId] = [
                'annual' => $productPrice['annual'] ?? false,
                'price' => $productPrice['price']->amount,
                'custom_pricing' => $productPrice['custom_pricing'] ?? false,
            ];
        }

        $usagePricing = [];
        foreach ($this->usagePricing as $usageTypeName => $usagePrice) {
            $usagePricing[$usageTypeName] = [
                'threshold' => $usagePrice['threshold'],
                'unit_price' => $usagePrice['unit_price']->amount,
            ];
        }

        return [
            'features' => $this->features,
            'products' => $products,
            'productPrices' => (object) $productPrices,
            'replaceExistingProducts' => $this->replaceExistingProducts,
            'quota' => $this->quota,
            'usagePricing' => (object) $usagePricing,
            'billingInterval' => $this->billingInterval?->value,
        ];
    }

    public static function fromJson(object $input): self
    {
        $products = [];
        foreach ($input->products as $productId) {
            $products[] = Product::findOrFail($productId);
        }

        $productPrices = [];
        foreach ($input->productPrices as $productId => $productPrice) {
            $productPrice = (object) $productPrice;
            $productPrices[(int) $productId] = [
                'price' => new Money('usd', $productPrice->price),
                'annual' => $productPrice->annual ?? false,
                'custom_pricing' => $productPrice->custom_pricing ?? false,
            ];
        }

        $usagePricing = [];
        foreach ($input->usagePricing as $usageTypeName => $usagePrice) {
            $usagePrice = (object) $usagePrice;
            $usagePricing[(string) $usageTypeName] = [
                'threshold' => $usagePrice->threshold,
                'unit_price' => new Money('usd', $usagePrice->unit_price),
            ];
        }

        return new self(
            products: $products,
            productPrices: $productPrices,
            replaceExistingProducts: $input->replaceExistingProducts ?? false,
            features: (array) $input->features,
            quota: (array) $input->quota,
            usagePricing: $usagePricing,
            billingInterval: $input->billingInterval ? BillingInterval::from($input->billingInterval) : null
        );
    }
}
