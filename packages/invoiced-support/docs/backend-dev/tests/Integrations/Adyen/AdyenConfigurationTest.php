<?php

namespace App\Tests\Integrations\Adyen;

use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Models\PricingConfiguration;
use App\Tests\AppTestCase;

class AdyenConfigurationTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testGetPricingForAccount(): void
    {
        $adyenAccount = new AdyenAccount();
        $pricingConfiguration1 = AdyenConfiguration::getPricingForAccount(true, $adyenAccount);
        $this->assertNull($pricingConfiguration1->id);

        $pricingConfiguration2 = new PricingConfiguration(['id' => 1]);
        $adyenAccount->pricing_configuration = $pricingConfiguration2;
        $pricingConfiguration3 = AdyenConfiguration::getPricingForAccount(true, $adyenAccount);
        $this->assertEquals($pricingConfiguration3, $pricingConfiguration2);
    }

    /**
     * @dataProvider pricingProvider
     */
    public function testGetStandardPricing(bool $liveMode, string $country, string $currency, array $pricing): void
    {
        $this->assertEquals($pricing, AdyenConfiguration::getStandardPricing($liveMode, $country, $currency));
    }

    public function pricingProvider(): array
    {
        return [
            // US Test Mode
            [
                false, // live mode
                'US', // country
                'usd', // currency
                [
                    'merchant_account' => 'InvoicedCOM',
                    'currency' => 'usd',
                    'card_variable_fee' => 2.9,
                    'card_international_added_variable_fee' => 1.0,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => 0.8,
                    'ach_max_fee' => 5,
                    'chargeback_fee' => 15,
                ],
            ],
            // US Live Mode
            [
                true, // live mode
                'US', // country
                'usd', // currency
                [
                    'merchant_account' => 'Flywire_Invoiced_ECOM',
                    'currency' => 'usd',
                    'card_variable_fee' => 2.9,
                    'card_international_added_variable_fee' => 1.0,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => 0,
                    'ach_variable_fee' => 0.8,
                    'ach_max_fee' => 5,
                    'chargeback_fee' => 15,
                ],
            ],
            // EU Test Mode
            [
                false, // live mode
                'AT', // country
                'eur', // currency
                [
                    'merchant_account' => 'InvoicedCOM',
                    'currency' => 'eur',
                    'card_variable_fee' => 1.9,
                    'card_international_added_variable_fee' => 1.4,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // EU Live Mode
            [
                true, // live mode
                'AT', // country
                'eur', // currency
                [
                    'merchant_account' => 'Flywr_Invoiced_EU_ECOM',
                    'currency' => 'eur',
                    'card_variable_fee' => 1.9,
                    'card_international_added_variable_fee' => 1.4,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // Canada Test Mode
            [
                false, // live mode
                'CA', // country
                'cad', // currency
                [
                    'merchant_account' => 'Invoiced_CANADA_TEST',
                    'currency' => 'cad',
                    'card_variable_fee' => 2.5,
                    'card_international_added_variable_fee' => 1.0,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // Canada Live Mode
            [
                true, // live mode
                'CA', // country
                'cad', // currency
                [
                    'merchant_account' => 'Flywr_Invoiced_Canada_ECOM',
                    'currency' => 'cad',
                    'card_variable_fee' => 2.5,
                    'card_international_added_variable_fee' => 1.0,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // UK Test Mode
            [
                false, // live mode
                'GB', // country
                'gbp', // currency
                [
                    'merchant_account' => 'InvoicedCOM',
                    'currency' => 'gbp',
                    'card_variable_fee' => 1.5,
                    'card_international_added_variable_fee' => 1.5,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // UK Live Mode
            [
                true, // live mode
                'GB', // country
                'gbp', // currency
                [
                    'merchant_account' => 'Flywr_Invoiced_UK_ECOM',
                    'currency' => 'gbp',
                    'card_variable_fee' => 1.5,
                    'card_international_added_variable_fee' => 1.5,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // Australia Test Mode
            [
                false, // live mode
                'AU', // country
                'aud', // currency
                [
                    'merchant_account' => 'InvoicedCOM',
                    'currency' => 'aud',
                    'card_variable_fee' => 1.7,
                    'card_international_added_variable_fee' => 1.5,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // Australia Live Mode
            [
                true, // live mode
                'AU', // country
                'aud', // currency
                [
                    'merchant_account' => 'Flywr_Invoiced_AU_ECOM',
                    'currency' => 'aud',
                    'card_variable_fee' => 1.7,
                    'card_international_added_variable_fee' => 1.5,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // New Zealand Test Mode
            [
                false, // live mode
                'NZ', // country
                'nzd', // currency
                [
                    'merchant_account' => 'InvoicedCOM',
                    'currency' => 'nzd',
                    'card_variable_fee' => 2.0,
                    'card_international_added_variable_fee' => 1.25,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
            // New Zealand Live Mode
            [
                true, // live mode
                'NZ', // country
                'nzd', // currency
                [
                    'merchant_account' => 'Flywr_Invoiced_NZ_ECOM',
                    'currency' => 'nzd',
                    'card_variable_fee' => 2.0,
                    'card_international_added_variable_fee' => 1.25,
                    'card_fixed_fee' => null,
                    'ach_fixed_fee' => null,
                    'ach_variable_fee' => null,
                    'ach_max_fee' => null,
                    'chargeback_fee' => 15,
                ],
            ],
        ];
    }

    /**
     * @dataProvider merchantAccountProvider
     */
    public function testMerchantAccount(bool $liveMode, string $country, string $merchantAccount): void
    {
        $this->assertEquals($merchantAccount, AdyenConfiguration::getMerchantAccount($liveMode, $country));
    }

    public function merchantAccountProvider(): array
    {
        return [
            [
                true, // live mode
                'AT', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'AU', // country,
                'Flywr_Invoiced_AU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'BE', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'BG', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'CA', // country,
                'Flywr_Invoiced_Canada_ECOM', // merchant account
            ],
            [
                true, // live mode
                'CH', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'CY', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'CZ', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'DE', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'DK', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'EE', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'ES', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'FI', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'FR', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'GB', // country,
                'Flywr_Invoiced_UK_ECOM', // merchant account
            ],
            [
                true, // live mode
                'GG', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'GI', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'GR', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'HK', // country,
                'Flywr_Invoiced_HongKong_ECOM', // merchant account
            ],
            [
                true, // live mode
                'HR', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'HU', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'IE', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'IM', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'IT', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'JE', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'LI', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'LT', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'LU', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'LV', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'MT', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'NL', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'NO', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'NZ', // country,
                'Flywr_Invoiced_NZ_ECOM', // merchant account
            ],
            [
                true, // live mode
                'PL', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'PR', // country,
                'Flywire_Invoiced_ECOM', // merchant account
            ],
            [
                true, // live mode
                'PT', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'RO', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'SE', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'SG', // country,
                'Flywr_Invoiced_Singapore_ECOM', // merchant account
            ],
            [
                true, // live mode
                'SI', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'SK', // country,
                'Flywr_Invoiced_EU_ECOM', // merchant account
            ],
            [
                true, // live mode
                'US', // country,
                'Flywire_Invoiced_ECOM', // merchant account
            ],
            // Test Mode
            [
                false, // live mode
                'US', // country
                'InvoicedCOM',
            ],
            [
                false, // live mode
                'CA', // country
                'Invoiced_CANADA_TEST', // merchant account
            ],
        ];
    }

    /**
     * @dataProvider environmentProvider
     */
    public function testEnvironment(bool $liveMode, string $expected): void
    {
        $this->assertEquals($expected, AdyenConfiguration::getEnvironment($liveMode));
    }

    public function environmentProvider(): array
    {
        return [
            [true, 'live'],
            [false, 'test'],
        ];
    }

    /**
     * @dataProvider liableAccountHolderProvider
     */
    public function testLiableAccountHolder(bool $liveMode, string $expected): void
    {
        $this->assertEquals($expected, AdyenConfiguration::getLiableAccountHolder($liveMode));
    }

    public function liableAccountHolderProvider(): array
    {
        return [
            [true, 'AH32DGH223228N5M3TXD8G2VW'],
            [false, 'AH32CM9223228N5LZRBFR7CPD'],
        ];
    }
}
