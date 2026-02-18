<?php

namespace App\Companies\Traits;

use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;

/**
 * Provides convenience methods around money formatting
 * for tenant-owned models.
 */
trait MoneyTrait
{
    /**
     * Formats a number as a currency value for display.
     *
     * @param array $options formatting options
     */
    public function currencyFormat(float $num, array $options = []): string
    {
        $options = array_replace($this->moneyFormat(), $options);

        return MoneyFormatter::get()->currencyFormat($num, $this->currency, $options);
    }

    /**
     * Formats a number as a currency value for display in HTML.
     *
     * @param array $options formatting options
     */
    public function currencyFormatHtml(float $num, array $options = []): string
    {
        $options = array_replace($this->moneyFormat(), $options);

        return MoneyFormatter::get()->currencyFormatHtml($num, $this->currency, $options);
    }

    /**
     * Formats a money amount for display.
     *
     * @param array $options formatting options
     */
    public function formatMoney(Money $amount, array $options = []): string
    {
        $options = array_replace($this->moneyFormat(), $options);

        return MoneyFormatter::get()->format($amount, $options);
    }

    /**
     * Formats a money amount for use in HTML.
     *
     * @param array $options formatting options
     */
    public function formatMoneyHtml(Money $amount, array $options = []): string
    {
        $options = array_replace($this->moneyFormat(), $options);

        return MoneyFormatter::get()->formatHtml($amount, $options);
    }
}
