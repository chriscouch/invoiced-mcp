<?php

namespace App\CashApplication\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use Carbon\CarbonImmutable;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Type;

/**
 * This represents a customer's credit balance at a certain point in time.
 *
 * @property int    $transaction_id
 * @property int    $customer_id
 * @property int    $timestamp
 * @property string $currency
 * @property float  $balance
 */
class CreditBalance extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'transaction_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                in_array: false,
            ),
            'customer_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                in_array: false,
            ),
            'timestamp' => new Property(
                type: Type::DATE_UNIX,
            ),
            'currency' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'balance' => new Property(
                type: Type::FLOAT,
            ),
        ];
    }

    protected static function getIDProperties(): array
    {
        return ['transaction_id'];
    }

    /**
     * Looks up a customer's credit balance at a specific point in time.
     */
    public static function lookup(Customer $customer, ?string $currency = null, ?CarbonImmutable $timestamp = null): Money
    {
        if (!$currency) {
            $currency = $customer->currency ?? $customer->tenant()->currency;
        }

        $timestamp ??= CarbonImmutable::now();

        $query = self::where('customer_id', $customer->id())
            ->where('currency', $currency)
            ->where('timestamp', $timestamp->getTimestamp(), '<=');

        $balance = $query->oneOrNull();
        if (!$balance) {
            return Money::zero($currency);
        }

        return Money::fromDecimal($currency, $balance->balance);
    }

    public static function query(): Query
    {
        $query = parent::query();

        return $query->sort('timestamp DESC,transaction_id DESC');
    }
}
