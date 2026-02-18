<?php

namespace App\Utilities;

use App\Controller\Admin\BillingProfileCrudController;
use App\Entity\Invoiced\Product;
use Doctrine\Persistence\ObjectManager;

class ChangesetUtility
{
    public static function toFriendlyString(object $changeset, ObjectManager $objectManager): string
    {
        $parts = [];

        // Features
        if (isset($changeset->features)) {
            foreach ($changeset->features as $feature => $enabled) {
                $parts[] = 'Feature '.$feature.': '.($enabled ? 'On' : 'Off');
            }
        }

        // Replace existing products
        if (isset($changeset->replaceExistingProducts) && $changeset->replaceExistingProducts) {
            $parts[] = 'Remove Existing Products';
        }

        // Products
        if (isset($changeset->products)) {
            foreach ($changeset->products as $productId) {
                $parts[] = 'Install Product: '.self::getProductName((int) $productId, $objectManager);
            }
        }

        // Product Prices
        if (isset($changeset->productPrices)) {
            foreach ($changeset->productPrices as $productId => $productPrice) {
                $annual = $productPrice->annual ?? false;
                $parts[] = 'Product Pricing Plan: '.self::getProductName((int) $productId, $objectManager).' - $'.number_format($productPrice->price / 100, 2).'/'.($annual ? 'yr' : 'mo');
            }
        }

        // Quota
        if (isset($changeset->quota)) {
            foreach ($changeset->quota as $quotaType => $quota) {
                $parts[] = 'Set '.$quotaType.' quota: '.number_format($quota);
            }
        }

        // Usage Pricing
        if (isset($changeset->usagePricing)) {
            foreach ($changeset->usagePricing as $usageType => $usagePrice) {
                $parts[] = 'Usage Pricing Plan: '.$usageType.', '.number_format($usagePrice->threshold).' Included - $'.number_format($usagePrice->unit_price / 100, 2).'/each';
            }
        }

        // Billing Interval
        if (isset($changeset->billingInterval)) {
            $parts[] = 'Billing Interval: '.array_search($changeset->billingInterval, BillingProfileCrudController::BILLING_INTERVALS);
        }

        return implode("\n", $parts);
    }

    private static function getProductName(int $id, ObjectManager $objectManager): string
    {
        return $objectManager->getRepository(Product::class)
            ->find($id)
            ?->getName() ?? (string) $id;
    }
}
