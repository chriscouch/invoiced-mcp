<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\Item;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\Shipping;
use App\AccountsReceivable\Models\Tax;
use App\AccountsReceivable\ValueObjects\CalculatedInvoice;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;

class InvoiceCalculator
{
    private static array $subtotalFields = [
        'discounts' => Discount::class,
        'taxes' => Tax::class,
        'shipping' => Shipping::class,
    ];

    private static array $lineItemFields = [
        'discounts' => Discount::class,
        'taxes' => Tax::class,
    ];

    /**
     * Prepares, sanitizes, and validates invoice input for use in the calculate()
     * method. It generates a calculated invoice object, however, it DOES NOT
     * perform any calculation.
     *
     * @throws InvoiceCalculationException when invalid data is supplied
     */
    public static function prepare(string $currency, array $lines, array $discounts, array $taxes, array $shipping = []): CalculatedInvoice
    {
        // NOTE:
        // Internally this function passes around normalized
        // zero-decimal amounts based on the input currency.
        // The returned money amounts are normalized.

        $invoice = new CalculatedInvoice();
        $invoice->currency = $currency;
        $invoice->items = $lines;
        $invoice->discounts = $discounts;
        $invoice->taxes = $taxes;
        $invoice->shipping = $shipping;

        /* Line Items */

        foreach ($invoice->items as &$item) {
            $catalogItem = null;
            if (isset($item['catalog_item']) && is_string($item['catalog_item'])) {
                $catalogItem = Item::getCurrent($item['catalog_item']);
            }

            if ($item instanceof LineItem) {
                $item = $item->toArray();
            }

            if (!is_array($item)) {
                throw new InvoiceCalculationException('Line item must be an array');
            }

            // sanitize
            $item = LineItem::sanitize($item, $catalogItem);

            // expand rates
            foreach (self::$lineItemFields as $type => $model) {
                $item[$type] = $model::expandList($item[$type]);
            }
        }

        /* Subtotal */

        // expand rates
        foreach (self::$subtotalFields as $type => $model) {
            $invoice->$type = $model::expandList($invoice->$type);
        }

        return $invoice;
    }

    /**
     * Calculates properties that are computed from invoice details
     * NOTE fields that do not exist will not affect the calculation.
     *
     * @throws InvoiceCalculationException when the invoice cannot be calculated
     */
    public static function calculateInvoice(CalculatedInvoice $invoice): void
    {
        // NOTE:
        // Internally this function passes around normalized
        // zero-decimal amounts based on the input currency.
        // The returned money amounts are normalized.

        if ($invoice->calculated()) {
            throw new InvoiceCalculationException('This invoice has already been finalized.');
        }

        $invoice->subtotal = 0;
        $invoice->total = 0;
        $invoice->totalDiscounts = 0;
        $invoice->totalTaxes = 0;
        $invoice->rates = [
            'discounts' => [],
            'taxes' => [],
            'shipping' => [],
        ];

        $discountedSubtotal = 0;
        $discountExcluded = 0;
        $taxExcluded = 0;

        /* Line Items */

        foreach ($invoice->items as &$item) {
            // calculate line item amount
            $lineItemAmount = LineItem::calculateAmount($invoice->currency, $item);
            $item['amount'] = $lineItemAmount->amount;

            // determine amount excluded from discounts
            if (!$item['discountable']) {
                $discountExcluded += $item['amount'];
            }

            // determine amount excluded from taxes
            if (!$item['taxable']) {
                $taxExcluded += $item['amount'];
            }

            // apply discounts
            $calculatedDiscounts = self::applyDiscounts($invoice, $item['discounts'], $item['amount'], true);
            $net = -$calculatedDiscounts;

            // apply taxes
            [$calculatedTaxes, $taxMarkdown] = self::applyTaxes($invoice, $item['taxes'], $item['amount'] - $calculatedDiscounts, true);
            $net += $calculatedTaxes;
            $item['amount'] -= $taxMarkdown;

            $discountedSubtotal += $item['amount'] - $calculatedDiscounts;
            $invoice->subtotal += $item['amount'];
            $invoice->total += $net + $item['amount'];
            $invoice->totalDiscounts += $calculatedDiscounts;
            $invoice->totalTaxes += $calculatedTaxes;
        }

        /* Subtotal */

        // apply discounts
        $calculatedDiscounts = self::applyDiscounts($invoice, $invoice->discounts, $discountedSubtotal - $discountExcluded);
        $net = -$calculatedDiscounts;

        // apply taxes
        [$calculatedTaxes, $taxMarkdown] = self::applyTaxes($invoice, $invoice->taxes, $discountedSubtotal - $calculatedDiscounts - $taxExcluded);
        $net += $calculatedTaxes;

        // reduce the subtotal by the amount of tax calculated when tax inclusive pricing is used
        if ($taxMarkdown > 0) {
            $invoice->subtotal -= $taxMarkdown;
            $invoice->total -= $taxMarkdown;
        }

        // apply shipping (deprecated)
        $calculatedShipping = self::applyShipping($invoice, $invoice->shipping, $discountedSubtotal - $calculatedDiscounts);
        $net += $calculatedShipping;

        $invoice->total += $net;
        $invoice->totalDiscounts += $calculatedDiscounts;
        $invoice->totalTaxes += $calculatedTaxes;

        // Check for duplicate tax rates applied to line item and subtotal
        foreach ($invoice->rates['taxes'] as $entry) { /* @phpstan-ignore-line */
            if (!is_array($entry['tax_rate'])) {
                continue;
            }

            if ($entry['in_items'] && $entry['in_subtotal']) {
                $taxId = $entry['tax_rate']['id'];

                throw new InvoiceCalculationException('This document cannot be saved because a tax rate (ID: '.$taxId.') is being applied to a line item and the subtotal. Please make sure the tax rate is applied only once to save this document.');
            }
        }

        /* Order Overall Rates */

        foreach (self::$subtotalFields as $type => $model) {
            usort($invoice->rates[$type], [$model, 'compare']); /* @phpstan-ignore-line */

            // remove the `order` property used for sorting
            foreach ($invoice->rates[$type] as &$appliedRate) { /* @phpstan-ignore-line */
                unset($appliedRate['order']);
            }
        }
    }

    /**
     * Calculates properties that are computed from invoice details
     * NOTE fields that do not exist will not affect the calculation.
     *
     * @param string $currency  currency code amounts are in
     * @param array  $lines     line items
     * @param array  $discounts discount field ids
     * @param array  $taxes     tax field ids
     * @param array  $shipping  shipping field ids (deprecated)
     *
     * @throws InvoiceCalculationException when the invoice cannot be calculated
     */
    public static function calculate(string $currency, array $lines, array $discounts, array $taxes, array $shipping = []): CalculatedInvoice
    {
        // NOTE:
        // Internally this function passes around normalized
        // zero-decimal amounts based on the input currency.
        // The returned money amounts are denormalized, however,
        // one day it would be nice to store normalized amounts
        // instead of just using them during calculation

        $invoice = self::prepare($currency, $lines, $discounts, $taxes, $shipping);

        self::calculateInvoice($invoice);

        // denormalize and finalize the result
        return $invoice->denormalize()->finalize();
    }

    /**
     * This does the calculations of a set of discounts against a subtotal
     * and merges the results into the overall applied rates. The
     * discounts are passed by ref so the `amount` can be
     * updated. WARNING: All money amounts will be normalized to
     * zero-decimal form.
     *
     * @param bool $appliedToItem true when applied to an item, false when applied to the subtotal
     */
    private static function applyDiscounts(CalculatedInvoice $invoice, array &$discounts, int $subtotal, $appliedToItem = false): int
    {
        $calculatedDiscounts = 0;

        foreach ($discounts as &$discount) {
            if (!isset($discount['_calculated'])) {
                $discountAmount = Discount::calculateAmount($invoice->currency, $subtotal, $discount);
                $discount['amount'] = $discountAmount->amount;
                $discount['_calculated'] = true; // prevent this from being recalculated on future passes
            }

            $calculatedDiscounts += $discount['amount'];

            self::addToOverallRates($discount, 'discounts', $invoice->rates['discounts'], $appliedToItem);
        }

        return $calculatedDiscounts;
    }

    /**
     * This does the calculations of a set of taxes against a subtotal
     * and merges the results into the overall applied rates. The
     * taxes are passed by ref so the `amount` can be
     * updated. WARNING: All money amounts will be normalized to
     * zero-decimal form.
     *
     * @param bool $appliedToItem true when applied to an item, false when applied to the subtotal
     */
    private static function applyTaxes(CalculatedInvoice $invoice, array &$taxes, int $subtotal, $appliedToItem = false): array
    {
        $calculatedTaxes = 0;
        $totalMarkdown = 0;

        foreach ($taxes as &$tax) {
            if (!isset($tax['_calculated'])) {
                [$taxAmount, $markdown] = self::calculateTaxAmount($invoice->currency, $subtotal, $tax);
                $tax['amount'] = $taxAmount;
                $totalMarkdown += $markdown;
                $tax['_calculated'] = true; // prevent this from being recalculated on future passes
            }

            $calculatedTaxes += $tax['amount'];

            self::addToOverallRates($tax, 'taxes', $invoice->rates['taxes'], $appliedToItem);
        }

        return [$calculatedTaxes, $totalMarkdown];
    }

    /**
     * Calculates the amount of a given applied rate array and
     * returns the zero-decimal amount.
     *
     * @param string $currency currency code amounts are in
     * @param int    $subtotal normalized amount to apply to
     */
    public static function calculateTaxAmount(string $currency, int $subtotal, array $appliedRate): array
    {
        // check if there was a rate applied
        $taxRateId = $appliedRate['tax_rate']['id'] ?? null;
        if ($taxRateId && 'AVATAX' != $taxRateId) {
            // Handle tax inclusive pricing
            if ($appliedRate['tax_rate']['inclusive'] ?? false) {
                $taxAmount = self::calculateTaxInclusiveAmount($currency, $subtotal, $appliedRate['tax_rate']);

                return [$taxAmount, $taxAmount];
            }

            // Handle tax exclusive pricing
            $taxAmount = self::calculateTaxExclusiveAmount($currency, $subtotal, $appliedRate['tax_rate']);

            return [$taxAmount, 0];
        }

        if (isset($appliedRate['amount'])) {
            $taxAmount = Money::fromDecimal($currency, (float) $appliedRate['amount']);

            return [$taxAmount->amount, 0];
        }

        return [0, 0];
    }

    public static function calculateTaxInclusiveAmount(string $currency, float $amount, array $taxRate): int
    {
        // On a percentage basis:
        // Subtotal = Total Amount / (1 + Tax Rate)               <-- Rounded Down
        // Tax Amount = Total Amount * Tax Rate / (1 + Tax Rate)  <-- Rounded Up
        // The tax amount is always rounded in favor of the tax agency.
        if ($taxRate['is_percent']) {
            $taxPercent = $taxRate['value'] / 100.0;
            $taxAmount = (int) ceil($amount * $taxPercent / (1 + $taxPercent));

            return $taxAmount;
        }

        // Using a flat amount:
        // Subtotal = Total Amount - Tax Amount
        // Tax amount is known
        $taxAmount = Money::fromDecimal($currency, (float) $taxRate['value']);

        return $taxAmount->amount;
    }

    public static function calculateTaxExclusiveAmount(string $currency, float $amount, array $taxRate): int
    {
        $value = (float) $taxRate['value'];

        if ($taxRate['is_percent']) {
            $taxAmount = (int) round(max(0, $amount) * ($value / 100.0));

            return $taxAmount;
        }

        $taxAmount = Money::fromDecimal($currency, $value);

        return $taxAmount->amount;
    }

    /**
     * This does the calculations of a set of shipping against a subtotal
     * and merges the results into the overall applied rates. The
     * shipping rates are passed by ref so the `amount` can be
     * updated. WARNING: All money amounts will be normalized to
     * zero-decimal form.
     *
     * @param bool $appliedToItem true when applied to an item, false when applied to the subtotal
     */
    private static function applyShipping(CalculatedInvoice $invoice, array &$shipping, int $subtotal, bool $appliedToItem = false): int
    {
        $calculatedShipping = 0;

        foreach ($shipping as &$shipping2) {
            if (!isset($shipping2['_calculated'])) {
                $shippingAmount = Shipping::calculateAmount($invoice->currency, $subtotal, $shipping2);
                $shipping2['amount'] = $shippingAmount->amount;
                $shipping2['_calculated'] = true; // prevent this from being recalculated on future passes
            }

            $calculatedShipping += $shipping2['amount'];

            self::addToOverallRates($shipping2, 'shipping', $invoice->rates['shipping'], $appliedToItem);
        }

        return $calculatedShipping;
    }

    private static function addToOverallRates(array $appliedRate, string $type, array &$overallRates, bool $appliedToItem): void
    {
        $scope = $appliedToItem ? 'items' : 'subtotal';

        $model = self::$subtotalFields[$type];
        $rateModel = $model::RATE_MODEL;
        $rateType = ObjectType::fromModelClass($rateModel)->typeName();

        // the overall rates are hashed by a key
        // if the applied rate has an attached rate then the key
        // is the rate ID, otherwise the key is based on the type
        // i.e. "*discounts" for a custom discount
        $k = "*$type";
        if ($appliedRate[$rateType] && isset($appliedRate[$rateType]['id'])) {
            $k = $appliedRate[$rateType]['id'];
        }

        if (!isset($overallRates[$k])) {
            $overallRates[$k] = array_replace([
                'in_items' => false,
                'in_subtotal' => false,
                'accumulated_total' => $appliedRate['amount'],
                // keep track of when it was inserted for sorting later
                'order' => count($overallRates) + 1,
            ], $appliedRate);

            // do not store the `amount` or `_calculated` properties in overall rates
            unset($overallRates[$k]['amount']);
            unset($overallRates[$k]['_calculated']);
        } else {
            $overallRates[$k]['accumulated_total'] += $appliedRate['amount'];
        }

        $overallRates[$k]['in_'.$scope] = true;
    }
}
