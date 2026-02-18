<?php

namespace App\SubscriptionBilling\Libs;

use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\PricingRules\TieredPricingRule;
use App\SubscriptionBilling\PricingRules\VolumePricingRule;

/**
 * Generates pricing for plans according to the
 * various pricing modes supported, like per unit,
 * tiered, and volume pricing.
 */
final class PricingEngine
{
    /**
     * Generates the line items with pricing
     * for a given plan and quantity.
     *
     * @throws PricingException when the pricing mode is not supported
     */
    public function price(Plan $plan, float $quantity, ?float $amount = null): array
    {
        $lineItem = $plan->lineItem();
        $lineItem['quantity'] = $quantity;

        if (Plan::PRICING_PER_UNIT == $plan->pricing_mode) {
            return [$lineItem];
        }

        if (Plan::PRICING_CUSTOM == $plan->pricing_mode) {
            // verify amount
            // strict equals required to allow value of 0.0
            if (null === $amount) {
                throw new PricingException('Plans priced with pricing mode \''.Plan::PRICING_CUSTOM.'\' require an amount value');
            }

            // custom plan price based on subscription amount
            $lineItem['unit_cost'] = $amount;

            return [$lineItem];
        }

        if (Plan::PRICING_VOLUME == $plan->pricing_mode) {
            $pricingRule = new VolumePricingRule();
            $tiers = [];
            // convert each element to an object
            foreach ((array) $plan->tiers as $tier) {
                $tiers[] = (object) $tier;
            }

            return $pricingRule->transform($lineItem, $tiers);
        }

        if (Plan::PRICING_TIERED == $plan->pricing_mode) {
            $pricingRule = new TieredPricingRule();
            $tiers = [];
            // convert each element to an object
            foreach ((array) $plan->tiers as $tier) {
                $tiers[] = (object) $tier;
            }

            return $pricingRule->transform($lineItem, $tiers);
        }

        throw new PricingException('Pricing mode not supported: '.$plan->pricing_mode);
    }
}
