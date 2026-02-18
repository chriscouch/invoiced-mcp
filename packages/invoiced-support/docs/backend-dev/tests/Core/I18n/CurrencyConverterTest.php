<?php

namespace App\Tests\Core\I18n;

use App\Core\I18n\CurrencyConverter;
use App\Core\I18n\Exception\CurrencyConversionException;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;

class CurrencyConverterTest extends AppTestCase
{
    private function getConverter(): CurrencyConverter
    {
        return new CurrencyConverter(self::getService('test.cache'), 'secret');
    }

    public function testConvert(): void
    {
        $converter = $this->getConverter();

        $amount = new Money('usd', 10000);
        $converted = $converter->convert($amount, 'usd');
        $this->assertEquals('usd', $converted->currency);
        $this->assertEquals(10000, $converted->amount);

        CurrencyConverter::$rates['EURUSD'] = 1 / 1.11;
        $amount = new Money('usd', 10000);
        $converted = $converter->convert($amount, 'eur');
        $this->assertEquals('eur', $converted->currency);
        $this->assertEquals(9009, $converted->amount);
    }

    public function testGetConversionRate(): void
    {
        $converter = $this->getConverter();

        $this->assertEquals(1, $converter->getConversionRateFrom('usd', 'usd'));

        CurrencyConverter::$rates['USDBLH'] = 0.1;
        $this->assertEquals(0.1, $converter->getConversionRateFrom('BLH', 'usd'));

        CurrencyConverter::$rates['USDEUR'] = 1.11;
        $this->assertEquals(1.11, $converter->getConversionRateFrom('eur', 'usd'));
    }

    public function testGetConversionRateInvalidCurrency(): void
    {
        $this->expectException(CurrencyConversionException::class);
        $converter = $this->getConverter();
        $this->assertNull($converter->getConversionRateFrom('blah', 'usd'));
    }

    public function testGetConversionRateRedis(): void
    {
        if (isset(CurrencyConverter::$rates['USDEUR'])) {
            unset(CurrencyConverter::$rates['USDEUR']);
        }

        $cache = self::getService('test.cache');
        $cache->delete('currency_conversion_rate_USDEUR');
        $cache->get('currency_conversion_rate_USDEUR', function () { return 2; });

        $converter = $this->getConverter();

        $this->assertEquals(2, $converter->getConversionRateFrom('eur', 'usd'));
    }
}
