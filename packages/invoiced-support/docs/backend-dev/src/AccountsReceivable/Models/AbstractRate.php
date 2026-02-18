<?php

namespace App\AccountsReceivable\Models;

use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property string      $name
 * @property bool        $is_percent
 * @property string|null $currency
 * @property float       $value
 */
abstract class AbstractRate extends PricingObject
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
                validate: ['callable', 'fn' => [self::class, 'validateName']],
            ),
            'is_percent' => new Property(
                type: Type::BOOLEAN,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                default: true,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency'], 'nullable' => true],
            ),
            'value' => new Property(
                type: Type::FLOAT,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: 'numeric',
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['metadata'] = $this->metadata;

        return $result;
    }

    //
    // Accessors
    //

    /**
     * Gets the currency property.
     *
     * @param string $currency
     */
    public function getCurrencyValue($currency): ?string
    {
        if ($this->is_percent) {
            return null;
        }

        return $currency;
    }

    //
    // Validators
    //

    /**
     * Validates a rate name.
     * Can be alpha-numeric with dashes, underscores, spaces, and percent signs.
     */
    public static function validateName(string $value): bool
    {
        return preg_match("/^[\w\-\s\%\_]*$/", $value) && strlen($value) >= 1;
    }

    //
    // Helpers
    //

    /**
     * Applies a given rate to an amount.
     *
     * @param string $currency currency code amounts are in
     * @param int    $amount   normalized input amount
     * @param array  $rate     input rate
     */
    public static function applyRateToAmount(string $currency, int $amount, array $rate): Money
    {
        $value = (float) $rate['value'];

        if ($rate['is_percent']) {
            return new Money($currency, (int) round(max(0, $amount) * ($value / 100.0)));
        }

        return Money::fromDecimal($currency, $value);
    }

    /**
     * Expands a list of rate IDs.
     *
     * @param array $rates list of rate IDs
     */
    public static function expandList(array $rates = []): array
    {
        $expanded = [];
        $usedIds = []; // prevent duplicates
        foreach ($rates as $id) {
            if (!is_array($id)) {
                $rate = static::getCurrent($id);
                if (!$rate) {
                    continue;
                }

                $expandedRate = $rate->toArray();
            } else {
                $expandedRate = $id;
            }

            if (!isset($expandedRate['id']) || in_array($expandedRate['id'], $usedIds)) {
                continue;
            }

            $expanded[] = $expandedRate;
            $usedIds[] = $expandedRate['id'];
        }

        return $expanded;
    }
}
