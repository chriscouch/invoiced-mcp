<?php

namespace App\Integrations\Adyen;

use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\Models\PricingConfiguration;

class AdyenPricingEngine
{
    /**
     * Prices a card transaction if it falls under a pricing
     * scenario that is not covered by the split configuration.
     * A null value means that the split configuration should handle
     * pricing the transaction.
     */
    public static function priceCardTransaction(PricingConfiguration $pricingConfiguration, Company $company, Money $amount, string $cardCountry, bool $isAmex): ?Money
    {
        // Never price Amex transactions when there is Amex-specific interchange++ pricing
        if ($isAmex && $pricingConfiguration->amex_interchange_variable_markup) {
            return null;
        }

        // Price the card transaction if this is an international card
        // on a blended rate with an added international fee.
        $internationalAddedFee = $pricingConfiguration->card_international_added_variable_fee;
        if ($internationalAddedFee > 0 && $cardCountry != $company->country) {
            // Add the variable fee
            $variableFee = $pricingConfiguration->card_variable_fee + $internationalAddedFee;
            $price = Money::fromDecimal(
                $amount->currency,
                $amount->toDecimal() * $variableFee / 100
            );

            // Add the fixed fee
            if ($fixedFee = $pricingConfiguration->card_fixed_fee) {
                $price = $price->add(
                    Money::fromDecimal($amount->currency, $fixedFee)
                );
            }

            return $price;
        }

        return null;
    }

    /**
     * Prices an ACH Direct Debit transaction.
     */
    public static function priceAchTransaction(PricingConfiguration $pricingConfiguration, Money $amount): ?Money
    {
        // Price the ACH transaction if this has a fee cap.
        $maxFee = $pricingConfiguration->ach_max_fee;
        if ($maxFee > 0) {
            // Add the variable fee
            $price = Money::fromDecimal(
                $amount->currency,
                $amount->toDecimal() * $pricingConfiguration->ach_variable_fee / 100
            );

            // Add the fixed fee
            if ($fixedFee = $pricingConfiguration->ach_fixed_fee) {
                $price = $price->add(
                    Money::fromDecimal($amount->currency, $fixedFee)
                );
            }

            // Return the minimum of the price or the cap.
            return $price->min(
                Money::fromDecimal($amount->currency, $maxFee)
            );
        }

        return null;
    }

    /**
     * Prices a Credit Card transaction.
     */
    public static function priceCreditCardTransaction(PricingConfiguration $pricingConfiguration, Money $amount): ?Money
    {
        $finalFee = Money::fromDecimal(
            $amount->currency,
            0
        );

        // Add the variable fee
        if ($pricingConfiguration->card_variable_fee && $pricingConfiguration->card_variable_fee > 0) {
            $price = Money::fromDecimal(
                $amount->currency,
                $amount->toDecimal()
            );

            $finalFee = $price->multiply(
                Money::fromDecimal($finalFee->currency, $pricingConfiguration->card_variable_fee)
            );

            $finalFee = $finalFee->divide(
                Money::fromDecimal($finalFee->currency, 100)
            );
        }

        // Add the fixed fee
        if ($fixedFee = $pricingConfiguration->card_fixed_fee) {
            $finalFee = $finalFee->add(
                Money::fromDecimal($finalFee->currency, $fixedFee)
            );
        }

        return $finalFee;
    }
}
