<?php

namespace App\Tests\Integrations\Adyen;

use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\AdyenPricingEngine;
use App\Integrations\Adyen\Models\PricingConfiguration;
use App\Tests\AppTestCase;

class AdyenPricingEngineTest extends AppTestCase
{
    /**
     * @dataProvider provideCards
     */
    public function testPriceCardTransaction(PricingConfiguration $pricingConfiguration, Money $amount, string $cardCountry, bool $isAmex, ?Money $expected): void
    {
        $company = new Company(['country' => 'US']);
        $this->assertEquals($expected, AdyenPricingEngine::priceCardTransaction($pricingConfiguration, $company, $amount, $cardCountry, $isAmex));
    }

    public function provideCards(): array
    {
        return [
            // Blended pricing with no international rate on international card
            [
                new PricingConfiguration([
                    'card_variable_fee' => 2.9,
                    'card_fixed_fee' => 0.3,
                ]),
                new Money('usd', 10000),
                'CH',
                false,
                null,
            ],
            // Interchange++ with no international rate on international card
            [
                new PricingConfiguration([
                    'card_variable_fee' => 0.3,
                    'card_fixed_fee' => 0.1,
                    'card_interchange_passthrough' => true,
                ]),
                new Money('usd', 10000),
                'CH',
                false,
                null,
            ],
            // Blended pricing with international rate on domestic card
            [
                new PricingConfiguration([
                    'card_variable_fee' => 2.9,
                    'card_international_added_variable_fee' => 1.0,
                    'card_fixed_fee' => 0.3,
                ]),
                new Money('usd', 10000),
                'US',
                false,
                null,
            ],
            // Blended pricing with international rate on international card
            [
                new PricingConfiguration([
                    'card_variable_fee' => 2.9,
                    'card_international_added_variable_fee' => 1.0,
                    'card_fixed_fee' => 0.3,
                ]),
                new Money('usd', 10000),
                'CH',
                false,
                new Money('usd', 420),
            ],
            // Amex-specific pricing with international rate on international Amex card
            [
                new PricingConfiguration([
                    'card_variable_fee' => 2.9,
                    'card_international_added_variable_fee' => 1.0,
                    'amex_interchange_variable_markup' => 0.02,
                    'card_fixed_fee' => 0.3,
                ]),
                new Money('usd', 10000),
                'CA',
                true,
                null,
            ],
            // Amex-specific pricing on domestic Amex card
            [
                new PricingConfiguration([
                    'card_variable_fee' => 2.9,
                    'card_international_added_variable_fee' => 1.0,
                    'amex_interchange_variable_markup' => 0.02,
                    'card_fixed_fee' => 0.3,
                ]),
                new Money('usd', 10000),
                'US',
                true,
                null,
            ],
        ];
    }

    /**
     * @dataProvider provideAch
     */
    public function testPriceAchTransaction(PricingConfiguration $pricingConfiguration, Money $amount, ?Money $expected): void
    {
        $this->assertEquals($expected, AdyenPricingEngine::priceAchTransaction($pricingConfiguration, $amount));
    }

    public function provideAch(): array
    {
        return [
            // Fixed fee, no cap
            [
                new PricingConfiguration([
                    'ach_fixed_fee' => 2.0,
                ]),
                new Money('usd', 10000),
                null,
            ],
            // Variable fee, no cap
            [
                new PricingConfiguration([
                    'ach_variable_fee' => 0.8,
                ]),
                new Money('usd', 10000),
                null,
            ],
            // Variable fee with cap, less than cap
            [
                new PricingConfiguration([
                    'ach_variable_fee' => 0.8,
                    'ach_max_fee' => 5,
                ]),
                new Money('usd', 10000),
                new Money('usd', 80),
            ],
            // Variable fee with cap, price exceeds cap
            [
                new PricingConfiguration([
                    'ach_variable_fee' => 0.8,
                    'ach_max_fee' => 5,
                ]),
                new Money('usd', 100000),
                new Money('usd', 500),
            ],
            // Variable + fixed fee with cap, price less than cap
            [
                new PricingConfiguration([
                    'ach_variable_fee' => 0.8,
                    'ach_fixed_fee' => 1,
                    'ach_max_fee' => 5,
                ]),
                new Money('usd', 10000),
                new Money('usd', 180),
            ],
            // Variable + fixed fee with cap, price exceeds cap
            [
                new PricingConfiguration([
                    'ach_variable_fee' => 0.8,
                    'ach_fixed_fee' => 1,
                    'ach_max_fee' => 5,
                ]),
                new Money('usd', 100000),
                new Money('usd', 500),
            ],
        ];
    }

    /**
     * @dataProvider provideCreditCards
     */
    public function testPriceCreditCardTransaction(PricingConfiguration $pricingConfiguration, Money $amount, ?Money $expected): void
    {
        $this->assertEquals($expected, AdyenPricingEngine::priceCreditCardTransaction($pricingConfiguration, $amount));
    }

    public function provideCreditCards(): array
    {
        return [
            // Card fixed fee is 0 - no changes
            [
                new PricingConfiguration([
                    'card_fixed_fee' => 0,
                ]),
                new Money('usd', 10000),
                new Money('usd', 0),
            ],
            // Card variable fee is missing - no changes
            [
                new PricingConfiguration([
                    'card_fixed_fee' => 2.0,
                ]),
                new Money('usd', 10000),
                new Money('usd', 200),
            ],
            // Card variable fee is 0 - no changes
            [
                new PricingConfiguration([
                    'card_fixed_fee' => 2.0,
                    'card_variable_fee' => 0
                ]),
                new Money('usd', 10000),
                new Money('usd', 200),
            ],
            // Card variable fee is null - no changes
            [
                new PricingConfiguration([
                    'card_fixed_fee' => 2.0,
                    'card_variable_fee' => null
                ]),
                new Money('usd', 10000),
                new Money('usd', 200),
            ],
            // Card variable fee is positive - price increased
            [
                new PricingConfiguration([
                    'card_fixed_fee' => 2.0,
                    'card_variable_fee' => 5.0
                ]),
                new Money('usd', 10000),
                new Money('usd', 700),
            ],
            // Card variable fee is negative - no changes
            [
                new PricingConfiguration([
                    'card_fixed_fee' => 2.0,
                    'card_variable_fee' => -5.0
                ]),
                new Money('usd', 10000),
                new Money('usd', 200),
            ],
        ];
    }
}
