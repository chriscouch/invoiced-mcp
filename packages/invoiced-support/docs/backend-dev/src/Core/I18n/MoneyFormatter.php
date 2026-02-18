<?php

namespace App\Core\I18n;

use App\Core\I18n\ValueObjects\Money;
use InvalidArgumentException;
use NumberFormatter;

/**
 * Formats money amounts.
 *
 * Formatting Options:
 * - `locale` - result locale
 * - `precision` - how many digits to include after the decimal
 * - `use_symbol` - when true prefixes with the currency symbol ($), when false prefixes with the currency code
 */
class MoneyFormatter
{
    private static MoneyFormatter $instance;

    private array $symbols = [];

    private array $decimals = [
        // Zero-decimal currencies:
        // https://en.wikipedia.org/wiki/ISO_4217
        'BIF' => 0,
        'CLP' => 0,
        'DJF' => 0,
        'GNF' => 0,
        'JPY' => 0,
        'KMF' => 0,
        'KRW' => 0,
        'MGA' => 0,
        'PYG' => 0,
        'RWF' => 0,
        'VUV' => 0,
        'XAF' => 0,
        'XOF' => 0,
        'XPF' => 0,
        // Bitcoin -> one satoshi has 8 decimal places
        'BTC' => 8,
        // Other
        'BHD' => 3,
    ];

    /**
     * Gets a singleton instance.
     */
    public static function get(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Looks up a currency symbol.
     */
    public function currencySymbol(string $currency): string
    {
        $currency = strtoupper($currency);

        if (!isset($this->symbols[$currency])) {
            try {
                $this->symbols[$currency] = Currencies::get($currency)['symbol'];
            } catch (InvalidArgumentException) {
                $this->symbols[$currency] = '';
            }
        }

        return $this->symbols[$currency];
    }

    /**
     * Gets the number of decimals a given currency should have.
     */
    public function numDecimals(string $currency): int
    {
        $currency = strtoupper($currency);

        // see if there is a preloaded decimal setting
        if (isset($this->decimals[$currency])) {
            return $this->decimals[$currency];
        }

        // currencies have 2 decimals by default
        return 2;
    }

    /**
     * Formats a number into a currency string for display.
     *
     * @param string $currency currency code
     * @param array  $options  formatting options
     */
    public function currencyFormat(float $num, string $currency, array $options = []): string
    {
        $currency = strtoupper($currency);
        $options = array_replace([
            'use_symbol' => true,
            'locale' => 'en_US',
        ], $options);

        $formatter = new NumberFormatter($options['locale'], NumberFormatter::CURRENCY);
        $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);

        // Set up the precision (# of decimals) of the formatted value
        if (isset($options['precision'])) {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $options['precision']);
        } else {
            $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $this->numDecimals($currency));
        }

        if (!$options['use_symbol']) {
            $formatter->setSymbol(NumberFormatter::CURRENCY_SYMBOL, $currency);
        }

        // Apply our own negative sign to the number because
        // the built-in formatting uses parentheses for negative #s.
        $negative = false;
        if (0 > $num) {
            $negative = true;
            $num *= -1;
        }

        // Note: we are using format() instead of formatCurrency()
        // in order to be able to override the decimal precision
        $value = (string) $formatter->format($num);

        return $negative ? '-'.$value : $value;
    }

    /**
     * Formats a number into a currency string for use in HTML.
     * Wraps components in <span> elements with classes.
     *
     * @param string $currency currency code
     * @param array  $options  formatting options
     */
    public function currencyFormatHtml(float $num, string $currency, array $options = []): string
    {
        return $this->currencyFormat($num, $currency, $options);
    }

    /**
     * Formats a money amount to a string.
     *
     * @param array $options formatting options
     */
    public function format(Money $money, array $options = []): string
    {
        return $this->currencyFormat(
            $money->toDecimal(),
            $money->currency,
            $options
        );
    }

    /**
     * Formats a money amount to a string for use in HTML.
     * Wraps components in <span> elements with classes.
     *
     * @param array $options formatting options
     */
    public function formatHtml(Money $money, array $options = []): string
    {
        return $this->currencyFormatHtml(
            $money->toDecimal(),
            $money->currency,
            $options
        );
    }

    /**
     * Normalizes a currency amount with a decimal to it's
     * zero-decimal form.
     *
     * @param string $currency currency code
     * @param float  $amount   amount to convert
     *
     * @return int normalized amount in smallest currency unit
     */
    public function normalizeToZeroDecimal(string $currency, float $amount): int
    {
        $precision = $this->numDecimals($currency);

        return (int) round($amount * pow(10, $precision));
    }

    /**
     * Denormalizes a currency amount from it's zero-decimal
     * form to a decimal amount.
     *
     * @param string $currency currency code
     * @param int    $amount   amount to convert
     */
    public function denormalizeFromZeroDecimal(string $currency, int $amount): float
    {
        $precision = $this->numDecimals($currency);

        return round($amount / pow(10, $precision), $precision);
    }

    /**
     * Rounds an amount to the precision specified by the
     * given currency.
     *
     * @param string $currency currency code
     */
    public function round(string $currency, float $amount): float
    {
        $precision = $this->numDecimals($currency);

        return round($amount, $precision);
    }
}
