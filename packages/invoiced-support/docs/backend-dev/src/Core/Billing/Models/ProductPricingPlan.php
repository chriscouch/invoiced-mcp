<?php

namespace App\Core\Billing\Models;

use App\Companies\Models\Company;
use App\Core\Entitlements\Models\Product;
use DateTimeInterface;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property Company           $tenant
 * @property int               $product_id
 * @property Product           $product
 * @property float             $price
 * @property bool              $annual
 * @property bool              $custom_pricing
 * @property DateTimeInterface $effective_date
 * @property DateTimeInterface $posted_on
 */
class ProductPricingPlan extends Model
{
    protected static function getProperties(): array
    {
        return [
            'tenant' => new Property(
                null: true,
                belongs_to: Company::class,
            ),
            'product' => new Property(
                required: true,
                belongs_to: Product::class,
            ),
            'price' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'annual' => new Property(
                type: Type::BOOLEAN,
                default: false
            ),
            'custom_pricing' => new Property(
                type: Type::BOOLEAN,
                default: false
            ),
            'effective_date' => new Property(
                type: Type::DATE,
                required: true,
            ),
            'posted_on' => new Property(
                type: Type::DATETIME,
                required: true,
            ),
        ];
    }

    /**
     * Gets the unique set of product pricing plans for each product.
     * This only considers the latest pricing for each product.
     *
     * @return self[]
     */
    public static function forCompany(Company $company): array
    {
        $result = [];
        $checkedPricing = [];
        $productPricingPlans = ProductPricingPlan::where('tenant_id', $company)
            ->sort('effective_date DESC')
            ->first(100);

        foreach ($productPricingPlans as $productPricingPlan) {
            $productId = $productPricingPlan->product_id;
            if (!isset($checkedPricing[$productId])) {
                $checkedPricing[$productId] = true;
                $result[] = $productPricingPlan;
            }
        }

        return $result;
    }
}
