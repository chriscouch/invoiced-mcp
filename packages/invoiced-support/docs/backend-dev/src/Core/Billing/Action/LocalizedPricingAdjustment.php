<?php

namespace App\Core\Billing\Action;

use App\Core\I18n\Countries;
use App\Core\I18n\CurrencyConverter;
use App\Core\I18n\ValueObjects\Money;
use Doctrine\DBAL\Connection;

class LocalizedPricingAdjustment
{
    public function __construct(
        private Connection $database,
        private CurrencyConverter $currencyConverter,
    ) {
    }

    /**
     * Gets a localized pricing adjustment as a percent for a given country.
     */
    public function getLocalizedAdjustment(string $country): float
    {
        $countryDetails = (new Countries())->get($country);
        if (!$countryDetails) {
            return 0;
        }

        $currency = $countryDetails['currency'] ?? 'USD';
        $pppRate = (float) $this->database->fetchOne('SELECT conversion_rate FROM PurchaseParityConversionRates WHERE country=? ORDER BY year DESC LIMIT 1', [$country]);
        if (!$pppRate) {
            return 0;
        }

        $amount = Money::fromDecimal($currency, 100 * $pppRate);
        $currencyRate = $this->currencyConverter->convert($amount, 'usd');
        $discount = ($currencyRate->toDecimal() / 100) - 1;

        // Round to the nearest whole percent
        $discount = round($discount, 2);

        // Resulting discount cannot be more than 75%
        // and resulting increase cannot be more than 50%
        return max(-0.75, min($discount, 0.5));
    }

    public function applyAdjustment(Money $price, float $adjustment): Money
    {
        return Money::fromDecimal($price->currency, $price->toDecimal() * (1 + $adjustment));
    }
}
