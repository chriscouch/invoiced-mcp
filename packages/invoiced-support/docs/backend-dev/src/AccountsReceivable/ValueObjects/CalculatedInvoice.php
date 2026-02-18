<?php

namespace App\AccountsReceivable\ValueObjects;

use App\Core\I18n\MoneyFormatter;

/**
 * Represents a computed invoice.
 */
class CalculatedInvoice
{
    public string $currency;
    public array $items = [];
    public float $subtotal = 0;
    public float $total = 0;
    public float $totalDiscounts = 0;
    public float $totalTaxes = 0;
    public array $discounts = [];
    public array $taxes = [];
    public array $shipping = [];
    public array $rates = [];
    private bool $calculated = false;
    private bool $normalized = true;
    private MoneyFormatter $moneyFormatter;

    private static array $subtotalFields = [
        'discounts',
        'taxes',
        'shipping',
    ];

    private static array $lineItemFields = [
        'discounts',
        'taxes',
    ];

    public function __construct()
    {
        $this->moneyFormatter = MoneyFormatter::get();
    }

    /**
     * Checks if the invoice has been calculated.
     */
    public function calculated(): bool
    {
        return $this->calculated;
    }

    /**
     * Marks the invoice as calculated.
     *
     * @return $this
     */
    public function finalize()
    {
        $this->calculated = true;

        return $this;
    }

    /**
     * Checks if the currency amounts are normalized.
     */
    public function normalized(): bool
    {
        return $this->normalized;
    }

    /**
     * Denormalizes the currency amounts stored on this invoice.
     *
     * @return $this
     */
    public function denormalize()
    {
        if (!$this->normalized) {
            return $this;
        }

        $this->denormalizeItems();
        $this->denormalizeSubtotal();
        $this->normalized = false;

        return $this;
    }

    /**
     * Normalizes the currency amounts stored on this invoice.
     *
     * @return $this
     */
    public function normalize()
    {
        if ($this->normalized) {
            return $this;
        }

        $this->normalizeItems();
        $this->normalizeSubtotal();
        $this->normalized = true;

        return $this;
    }

    /**
     * Normalizes line items.
     */
    private function normalizeItems(): void
    {
        foreach ($this->items as &$item) {
            // denormalize line item amounts
            $item['amount'] = $this->moneyFormatter->normalizeToZeroDecimal($this->currency, (float) $item['amount']);

            // denormalize applied rate amounts
            foreach (self::$lineItemFields as $type) {
                foreach ($item[$type] as &$rate) {
                    $rate['amount'] = $this->moneyFormatter->normalizeToZeroDecimal($this->currency, (float) $rate['amount']);
                }
            }
        }
    }

    /**
     * Denormalizes line items.
     */
    private function denormalizeItems(): void
    {
        foreach ($this->items as &$item) {
            // denormalize line item amounts
            $item['amount'] = $this->moneyFormatter->denormalizeFromZeroDecimal($this->currency, (int) $item['amount']);

            // denormalize applied rate amounts
            foreach (self::$lineItemFields as $type) {
                foreach ($item[$type] as &$rate) {
                    $rate['amount'] = $this->moneyFormatter->denormalizeFromZeroDecimal($this->currency, (int) $rate['amount']);
                }
            }
        }
    }

    /**
     * Normalizes subtotal.
     */
    private function normalizeSubtotal(): void
    {
        foreach (self::$subtotalFields as $type) {
            // denormalize applied rate amounts
            foreach ($this->$type as &$rate) {
                $rate['amount'] = $this->moneyFormatter->normalizeToZeroDecimal($this->currency, (float) $rate['amount']);
            }

            // denormalize applied rate accumulated totals
            foreach ($this->rates[$type] as &$rate) {
                $rate['accumulated_total'] = $this->moneyFormatter->normalizeToZeroDecimal($this->currency, (float) $rate['accumulated_total']);
            }
        }

        // denormalize total amounts
        $this->subtotal = $this->moneyFormatter->normalizeToZeroDecimal($this->currency, (float) $this->subtotal);
        $this->total = $this->moneyFormatter->normalizeToZeroDecimal($this->currency, (float) $this->total);
        $this->totalDiscounts = $this->moneyFormatter->normalizeToZeroDecimal($this->currency, (float) $this->totalDiscounts);
        $this->totalTaxes = $this->moneyFormatter->normalizeToZeroDecimal($this->currency, (float) $this->totalTaxes);
    }

    /**
     * Denormalizes subtotal.
     */
    private function denormalizeSubtotal(): void
    {
        foreach (self::$subtotalFields as $type) {
            // denormalize applied rate amounts
            foreach ($this->$type as &$rate) {
                $rate['amount'] = $this->moneyFormatter->denormalizeFromZeroDecimal($this->currency, (int) $rate['amount']);
            }

            // denormalize applied rate accumulated totals
            foreach ($this->rates[$type] as &$rate) {
                $rate['accumulated_total'] = $this->moneyFormatter->denormalizeFromZeroDecimal($this->currency, (int) $rate['accumulated_total']);
            }
        }

        // denormalize total amounts
        $this->subtotal = $this->moneyFormatter->denormalizeFromZeroDecimal($this->currency, (int) $this->subtotal);
        $this->total = $this->moneyFormatter->denormalizeFromZeroDecimal($this->currency, (int) $this->total);
        $this->totalDiscounts = $this->moneyFormatter->denormalizeFromZeroDecimal($this->currency, (int) $this->totalDiscounts);
        $this->totalTaxes = $this->moneyFormatter->denormalizeFromZeroDecimal($this->currency, (int) $this->totalTaxes);
    }
}
