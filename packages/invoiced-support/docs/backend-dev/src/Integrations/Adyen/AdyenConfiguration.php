<?php

namespace App\Integrations\Adyen;

use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Models\PricingConfiguration;
use RuntimeException;

class AdyenConfiguration
{
    const AUTHENTICATION = 'authentication';
    const BALANCE_PLATFORM = 'balance_platform';
    const CHECKOUT = 'checkout';
    const DISPUTE = 'dispute';
    const KYC = 'kyc';
    const MANAGEMENT = 'management';
    const PAL = 'pal';

    private const array SUPPORTED_COUNTRIES = [
        // TODO: other countries are not currently configured with Adyen
        'AT',
//        'AU',
        'BE',
        'BG',
        'CA',
//        'CH',
        'CY',
        'CZ',
        'DE',
        'DK',
        'EE',
        'ES',
        'FI',
        'FR',
        'GB',
//        'GG',
//        'GI',
        'GR',
//        'HK',
        'HR',
        'HU',
        'IE',
//        'IM',
        'IT',
//        'JE',
//        'LI',
        'LT',
        'LU',
        'LV',
        'MT',
        'NL',
//        'NO',
//        'NZ',
        'PL',
//        'PR',
        'PT',
        'RO',
        'SE',
//        'SG',
        'SI',
        'SK',
        'US',
    ];

    private const EU_COUNTRIES = [
        'AT',
        'BE',
        'BG',
        'CY',
        'CZ',
        'DE',
        'DK',
        'EE',
        'ES',
        'FI',
        'FR',
        'GR',
        'HR',
        'HU',
        'IE',
        'IT',
        'LT',
        'LU',
        'LV',
        'MT',
        'NL',
        'PL',
        'PT',
        'RO',
        'SE',
        'SI',
        'SK',
    ];

    private const array INDUSTRY_MAPPING = [
        'Academia' => '6114', // Business schools, and computer and management training
        'Accounting' => '541211', // Offices of certified public accountants
        'Animal care' => '54194', // Veterinary services
        'Agriculture' => '11', // Agriculture, forestry, fishing, and hunting
        'Apparel' => '315', // Apparel manufacturing
        'Banking' => '56149', // Other business support services
        'Beauty and Cosmetics' => '44612', // Cosmetics, beauty supplies, and perfume stores
        'Biotechnology' => '54171', // Research and development in the Physical, Engineering, and Life Sciences
        'Business Services' => '56149', // Other business support services
        'Chemicals' => '4238', // Machinery, equipment, and supplies merchant wholesalers
        'Communications' => '5179', // Other telecommunications
        'Construction' => '23', // Construction & installation
        'Consulting' => '56149', // Other business support services
        'Distribution' => '4889', // Other support activities for transportation
        'Education' => '6114', // Business schools, and computer and management training
        'Electronics' => '42511', // Business-to-business electronic markets
        'Energy' => '2211', // Electric power generation, transmission, and distribution
        'Engineering' => '5413', // Architectural, engineering, and related services
        'Entertainment' => '7139C', // All other amusement and recreation industries
        'Environmental' => '54199', // All other professional, scientific, and technical services
        'Finance' => '56149', // Other business support services
        'Food and Beverage' => '311',  // Food manufacturing
        'Government and Public services' => '56149', // Other business support services
        'Grant and Fundraising' => '56149', // Other business support services
        'Healthcare' => '6219', // Other ambulatory health care services
        'Hospitality' => '7211B', // Other traveler accommodation
        'HR and Recruitment' => '5613', // Employment services
        'Insurance' => '56149', // Other business support services
        'Legal Services' => '5411A', // Lawyers (except bankruptcy)
        'Machinery' => '333', // Machinery manufacturing
        'Manufacturing' => '339D', // Other miscellaneous durable goods manufacturing
        'Marketing and Advertising' => '5418', // Advertising, public relations, and related services
        'Media' => '5112A', // Digital goods - audiovisual media including books, movies, and music
        'Medical Equipment and Supplies' => '446199', // All other health and personal care stores
        'Not for profit' => '54199', // All other professional, scientific, and technical services
        'Oil and Gas' => '2212', // Natural gas distribution
        'Pharmaceutical' => '54171', // Research and development in the Physical, Engineering, and Life Sciences
        'Real Estate' => '56149', // Other business support services
        'Recreation' => '61162', // Sports and recreation instruction
        'Recruitment' => '5613', // Employment services
        'Research (non-academic)' => '54171', // Research and development in the Physical, Engineering, and Life Sciences
        'Retail' => '453998', // All other miscellaneous store retailers
        'Security Services' => '5616A', // Security services
        'Shipping' => '4889', // Other support activities for transportation
        'Software and IT' => '51121', // Digital goods - software applications
        'Technology Hardware' => '42511', // Business-to-business electronic markets
        'Telecommunications' => '5179', // Other telecommunications
        'Transportation' => '4859', // Other transit and ground passenger transportation
        'Travel' => '7211B', // Other traveler accommodation
        'Utilities' => '2211', // Electric power generation, transmission, and distribution
        'Other' => '56149', // Other business support services
    ];

    private const string LIVE_LIABLE_ACCOUNTS = 'BA32DC2223228N5M3TXD8C6R3';

    private const string DEV_LIABLE_ACCOUNTS = 'BA32CQ3223228N5LZRBFRD3JG';

    public static function getLiableAccount(bool $liveMode): string
    {
        return $liveMode ? self::LIVE_LIABLE_ACCOUNTS : self::DEV_LIABLE_ACCOUNTS;
    }

    public static function getSupportedCounties(): array
    {
        return self::SUPPORTED_COUNTRIES;
    }

    public static function getIndustryCode(string $industry): ?string
    {
        return self::INDUSTRY_MAPPING[$industry] ?? null;
    }

    public static function getMerchantAccount(bool $liveMode, string $country): string
    {
        if ($liveMode) {
            if (in_array($country, self::EU_COUNTRIES)) {
                return 'Flywr_Invoiced_EU_ECOM';
            }

            return match ($country) {
                'CH', 'GG', 'GI', 'GR', 'IM', 'JE', 'LI', 'NO' => 'Flywr_Invoiced_EU_ECOM',
                'AU' => 'Flywr_Invoiced_AU_ECOM',
                'CA' => 'Flywr_Invoiced_Canada_ECOM',
                'HK' => 'Flywr_Invoiced_HongKong_ECOM',
                'NZ' => 'Flywr_Invoiced_NZ_ECOM',
                'SG' => 'Flywr_Invoiced_Singapore_ECOM',
                'GB' => 'Flywr_Invoiced_UK_ECOM',
                'US', 'PR' => 'Flywire_Invoiced_ECOM',
                default => throw new RuntimeException('Country not supported: '.$country),
            };
        }

        return match ($country) {
            'CA' => 'Invoiced_CANADA_TEST',
            // At the time of this writing, the test account can only board US merchants
            // but we need to provide a default value for our unit tests
            default => 'InvoicedCOM',
        };
    }

    public static function getMerchantRegion(bool $liveMode, string $country): string
    {
        if ($liveMode) {
            if (in_array($country, self::EU_COUNTRIES)) {
                return 'EU';
            }

            return match ($country) {
                'CH', 'GG', 'GI', 'GR', 'IM', 'JE', 'LI', 'NO', 'GB' => 'EU',
                'AU', 'HK', 'NZ', 'SG'=> 'AU',
                'CA', 'US', 'PR'  => 'NA',
                default => throw new RuntimeException('Country not supported: '.$country),
            };
        }

        return 'NA';
    }

    public static function getUrl(bool $liveMode, string $type): string
    {
        if ($liveMode) {
            return match ($type) {
                self::AUTHENTICATION => 'https://authe-live.adyen.com',
                self::BALANCE_PLATFORM => 'https://balanceplatform-api-live.adyen.com',
                self::CHECKOUT => 'https://0501fc703c2f6373-FlywirePaymentsLimited066-checkout-live.adyenpayments.com/checkout',
                self::DISPUTE => 'https://ca-live.adyen.com',
                self::KYC => 'https://kyc-live.adyen.com',
                self::MANAGEMENT => 'https://management-live.adyen.com',
                self::PAL => 'https://0501fc703c2f6373-flywirepaymentslimited066-pal-live.adyenpayments.com',
                default => '',
            };
        }

        return match ($type) {
            self::AUTHENTICATION => 'https://test.adyen.com',
            self::BALANCE_PLATFORM => 'https://balanceplatform-api-test.adyen.com',
            self::CHECKOUT => 'https://checkout-test.adyen.com',
            self::DISPUTE => 'https://ca-test.adyen.com',
            self::KYC => 'https://kyc-test.adyen.com',
            self::MANAGEMENT => 'https://management-test.adyen.com',
            self::PAL => 'https://pal-test.adyen.com',
            default => '',
        };
    }

    public static function getPricingForAccount(bool $liveMode, AdyenAccount $adyenAccount): PricingConfiguration
    {
        if ($pricingConfiguration = $adyenAccount->pricing_configuration) {
            return $pricingConfiguration;
        }

        $company = $adyenAccount->tenant();
        $parameters = self::getStandardPricing($liveMode, (string) $company->country, $company->currency);

        return new PricingConfiguration($parameters);
    }

    public static function getStandardPricing(bool $liveMode, string $country, string $currency): array
    {
        // Default / standard pricing
        $cardVariableFee = 2.9;
        $cardInternationalAddedVariableFee = 1;
        $cardFixedFee = null;
        $achFixedFee = null;
        $achVariableFee = null;
        $achMaxFee = null;
        $chargebackFee = 15;

        // US Pricing
        if ('US' == $country) {
            $achVariableFee = 0.8;
            $achMaxFee = 5;
        }

        // Canada Pricing
        if ('CA' == $country) {
            $cardVariableFee = 2.5;
            // International added is already 1%
        }

        // EU Pricing
        if (in_array($country, self::EU_COUNTRIES)) {
            $cardVariableFee = 1.9;
            $cardInternationalAddedVariableFee = 1.4;
        }

        // UK Pricing
        if ('GB' == $country) {
            $cardVariableFee = 1.5;
            $cardInternationalAddedVariableFee = 1.5;
        }

        // Australia Pricing
        if ('AU' == $country) {
            $cardVariableFee = 1.7;
            $cardInternationalAddedVariableFee = 1.5;
        }

        // New Zealand Pricing
        if ('NZ' == $country) {
            $cardVariableFee = 2;
            $cardInternationalAddedVariableFee = 1.25;
        }

        return [
            'merchant_account' => self::getMerchantAccount($liveMode, $country),
            'currency' => $currency,
            'card_variable_fee' => $cardVariableFee,
            'card_international_added_variable_fee' => $cardInternationalAddedVariableFee,
            'card_fixed_fee' => $cardFixedFee,
            'ach_fixed_fee' => $achFixedFee,
            'ach_variable_fee' => $achVariableFee,
            'ach_max_fee' => $achMaxFee,
            'chargeback_fee' => $chargebackFee,
        ];
    }

    public static function getEnvironment(bool $liveMode): string
    {
        return $liveMode ? 'live' : 'test';
    }

    public static function getLiableAccountHolder(bool $liveMode): string
    {
        return $liveMode ? 'AH32DGH223228N5M3TXD8G2VW' : 'AH32CM9223228N5LZRBFR7CPD';
    }
}
