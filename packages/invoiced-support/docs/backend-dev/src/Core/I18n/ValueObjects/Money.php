<?php

namespace App\Core\I18n\ValueObjects;

use App\Core\I18n\Exception\MismatchedCurrencyException;
use App\Core\I18n\MoneyFormatter;
use InvalidArgumentException;
use JsonSerializable;
use Money\Money as MoneyPhp;
use Stringable;

/**
 * This class represents normalized money amounts using
 * immutability. The normalized amount is the lowest denominated
 * unit for a given currency. In many cases this means cents.
 *
 * @property string $currency
 * @property int    $amount
 */
class Money implements JsonSerializable, Stringable
{
    private string $_currency;
    private int $_amount;

    /**
     * @param string $currency 3-digit currency code
     * @param int    $amount   normalized amount
     */
    public function __construct(string $currency, int $amount)
    {
        if (!$currency) {
            throw new InvalidArgumentException('Missing currency code');
        }

        $this->_currency = strtolower($currency);
        $this->_amount = $amount;
    }

    public static function zero(string $currency): self
    {
        return new self($currency, 0);
    }

    public static function fromDecimal(string $currency, float $amount): self
    {
        $normalized = MoneyFormatter::get()->normalizeToZeroDecimal($currency, $amount);

        return new self($currency, $normalized);
    }

    public static function fromMoneyPhp(MoneyPhp $amount): self
    {
        return new self($amount->getCurrency()->getCode(), (int) $amount->getAmount());
    }

    /**
     * Converts the amount to the denormalized decimal representation.
     */
    public function toDecimal(): float
    {
        return MoneyFormatter::get()->denormalizeFromZeroDecimal($this->_currency, $this->_amount);
    }

    /**
     * Returns true when the money amount is positive.
     */
    public function isPositive(): bool
    {
        return $this->_amount > 0;
    }

    /**
     * Returns true when the money amount is negative.
     */
    public function isNegative(): bool
    {
        return $this->_amount < 0;
    }

    /**
     * Returns true when the money amount is zero.
     *
     * @throws MismatchedCurrencyException when the currencies do not match
     */
    public function isZero(): bool
    {
        return 0 == $this->_amount;
    }

    /**
     * Checks if two money amounts share the same currency.
     */
    public function hasSameCurrency(self $money): bool
    {
        return $this->_currency == $money->_currency;
    }

    /**
     * Checks if two money amounts are equal.
     *
     * @throws MismatchedCurrencyException when the currencies do not match
     */
    public function equals(self $money): bool
    {
        $this->checkCurrenciesMatch($money);

        return $this->_amount == $money->_amount;
    }

    /**
     * Checks if a this money amount is greater than a given
     * money amount.
     *
     * @throws MismatchedCurrencyException when the currencies do not match
     */
    public function greaterThan(self $money): bool
    {
        $this->checkCurrenciesMatch($money);

        return $this->_amount > $money->_amount;
    }

    /**
     * Checks if a this money amount is greater than or equal to
     * a given money amount.
     *
     * @throws MismatchedCurrencyException when the currencies do not match
     */
    public function greaterThanOrEqual(self $money): bool
    {
        $this->checkCurrenciesMatch($money);

        return $this->_amount >= $money->_amount;
    }

    /**
     * Checks if a this money amount is less than a given
     * money amount.
     *
     * @throws MismatchedCurrencyException when the currencies do not match
     */
    public function lessThan(self $money): bool
    {
        $this->checkCurrenciesMatch($money);

        return $this->_amount < $money->_amount;
    }

    /**
     * Checks if a this money amount is less than or equal to
     * a given money amount.
     *
     * @throws MismatchedCurrencyException when the currencies do not match
     */
    public function lessThanOrEqual(self $money): bool
    {
        $this->checkCurrenciesMatch($money);

        return $this->_amount <= $money->_amount;
    }

    /**
     * Compares two money amounts (useful for sorting).
     */
    public function compare(self $money): int
    {
        $this->checkCurrenciesMatch($money);

        if ($this->_amount > $money->_amount) {
            return 1;
        } elseif ($this->_amount < $money->_amount) {
            return -1;
        }

        return 0;
    }

    /**
     * Adds a money amount to this money amount.
     *
     * @throws MismatchedCurrencyException when trying to add money amounts with a different currency
     */
    public function add(self $money): self
    {
        $this->checkCurrenciesMatch($money);

        return new self($this->_currency, $this->_amount + $money->_amount);
    }

    /**
     * Subtracts a money amount to this money amount.
     *
     * @throws MismatchedCurrencyException when trying to subtract money amounts with a different currency
     */
    public function subtract(self $money): self
    {
        $this->checkCurrenciesMatch($money);

        return new self($this->_currency, $this->_amount - $money->_amount);
    }

    /**
     * Multiplies a money amount to this money amount.
     *
     * @throws MismatchedCurrencyException when trying to multiply money amounts with a different currency
     */
    public function multiply(self $money): self
    {
        $this->checkCurrenciesMatch($money);

        return new self($this->_currency, $this->_amount * $money->_amount);
    }

    /**
     * Divides a money amount into this money amount.
     *
     * @throws MismatchedCurrencyException when trying to divide money amounts with a different currency
     */
    public function divide(self $money): self
    {
        $this->checkCurrenciesMatch($money);

        return new self($this->_currency, $this->_amount / $money->_amount);
    }

    /**
     * Gets the negated money amount.
     */
    public function negated(): self
    {
        return new self($this->_currency, -$this->_amount);
    }

    /**
     * Gets the absolute money amount.
     */
    public function abs(): self
    {
        return $this->_amount > 0 ? $this : new self($this->_currency, -$this->_amount);
    }

    /**
     * Gets the maximum money amount.
     */
    public function max(self $money): self
    {
        $this->checkCurrenciesMatch($money);

        return $this->lessThan($money) ? $money : $this;
    }

    /**
     * Gets the minimum money amount.
     */
    public function min(self $money): self
    {
        $this->checkCurrenciesMatch($money);

        return $this->greaterThan($money) ? $money : $this;
    }

    public function __get(string $k): mixed
    {
        if ('currency' == $k) {
            return $this->_currency;
        } elseif ('amount' == $k) {
            return $this->_amount;
        }

        return $this->$k;
    }

    public function __toString(): string
    {
        return $this->toDecimal().' '.strtoupper($this->_currency);
    }

    public function jsonSerialize(): array
    {
        return [
            'currency' => $this->_currency,
            'amount' => $this->_amount,
        ];
    }

    /**
     * Checks if the currency on a money amount matches the
     * currency on this money amount.
     *
     * @throws MismatchedCurrencyException when currencies do not match
     */
    private function checkCurrenciesMatch(self $money): void
    {
        if ($money->_currency != $this->_currency) {
            throw new MismatchedCurrencyException("{$money->_currency} does not match the expected currency: {$this->_currency}");
        }
    }

    /**
     * Returns the percentage from the money.
     */
    public function percent(float $percent): self
    {
        // simplified representation of $this->_amount / 100 * ($percent / 100) * 100
        return new self($this->_currency, (int) ($this->_amount * ($percent / 100)));
    }

    /**
     * Returns the reverse percentage from the money.
     * use minus value to calculate items original value with extra charge.
     */
    public function reversePercent(float $percent): self
    {
        // simplified representation of $this->_amount / 100 * (100 + $percent) * 100 * 100
        return new self($this->_currency, (int) ($this->_amount / (100 + $percent)) * 100);
    }
}
