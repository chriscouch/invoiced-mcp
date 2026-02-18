<?php

namespace App\Tests\Core\I18n;

use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;

class MoneyFormatterTest extends AppTestCase
{
    private static string $frenchSpace = ' ';
    private static string $frenchNarrowSpace = ' ';
    private static string $icuVersion;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$icuVersion = str_replace('ICU version => ', '', (string) exec('php -i | grep "ICU version"'));
        self::$frenchSpace = mb_chr(160);
        self::$frenchNarrowSpace = mb_chr(160);
        if (version_compare(self::$icuVersion, '56', '>')) {
            self::$frenchNarrowSpace = mb_chr(8239);
        }
    }

    public function testGet(): void
    {
        $instance = MoneyFormatter::get();
        $this->assertInstanceOf(MoneyFormatter::class, $instance);

        $this->assertEquals($instance, MoneyFormatter::get());
    }

    public function testCurrencySymbol(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals('$', $formatter->currencySymbol('usd'));
        $this->assertEquals('€', $formatter->currencySymbol('eur'));
    }

    public function testNumDecimals(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals(2, $formatter->numDecimals('usd'));
        $this->assertEquals(2, $formatter->numDecimals('eur'));
        $this->assertEquals(0, $formatter->numDecimals('jpy'));
        $this->assertEquals(8, $formatter->numDecimals('btc'));
    }

    public function testCurrencyFormat(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals('$100.00', $formatter->currencyFormat(100, 'usd'));
        $this->assertEquals('$1,000.24', $formatter->currencyFormat(1000.24, 'usd'));
        $this->assertEquals('-$123.00', $formatter->currencyFormat(-123, 'usd'));
        $this->assertEquals('¥1,000', $formatter->currencyFormat(1000.24, 'jpy'));
        if (version_compare(self::$icuVersion, '56', '>')) {
            $this->assertEquals('BTC 1,000.12345678', $formatter->currencyFormat(1000.12345678, 'btc'));
        } else {
            $this->assertEquals('BTC1,000.12345678', $formatter->currencyFormat(1000.12345678, 'btc'));
        }
    }

    public function testCurrencyFormatPrecision(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals('$100.0000', $formatter->currencyFormat(100, 'usd', ['precision' => 4]));
        $this->assertEquals('$1,000.2400', $formatter->currencyFormat(1000.24, 'usd', ['precision' => 4]));
        $this->assertEquals('-$123.0000', $formatter->currencyFormat(-123, 'usd', ['precision' => 4]));
        $this->assertEquals('¥1,000.2400', $formatter->currencyFormat(1000.24, 'jpy', ['precision' => 4]));
        if (version_compare(self::$icuVersion, '56', '>')) {
            $this->assertEquals('BTC 1,000.1235', $formatter->currencyFormat(1000.12345678, 'btc', ['precision' => 4]));
        } else {
            $this->assertEquals('BTC1,000.1235', $formatter->currencyFormat(1000.12345678, 'btc', ['precision' => 4]));
        }
    }

    public function testCurrencyFormatCurrencyCode(): void
    {
        $formatter = MoneyFormatter::get();

        if (version_compare(self::$icuVersion, '56', '>')) {
            $this->assertEquals('USD 100.00', $formatter->currencyFormat(100, 'usd', ['use_symbol' => false]));
            $this->assertEquals('-USD 100.00', $formatter->currencyFormat(-100, 'usd', ['use_symbol' => false]));
        } else {
            $this->assertEquals('USD100.00', $formatter->currencyFormat(100, 'usd', ['use_symbol' => false]));
            $this->assertEquals('-USD100.00', $formatter->currencyFormat(-100, 'usd', ['use_symbol' => false]));
        }
    }

    public function testCurrencyFormatEuroStyle(): void
    {
        $formatter = MoneyFormatter::get();

        $options = ['locale' => 'it_IT'];
        $this->assertEquals('100,00 €', $formatter->currencyFormat(100, 'eur', $options));
        $this->assertEquals('1.000,24 €', $formatter->currencyFormat(1000.24, 'eur', $options));

        $options = ['locale' => 'fr_FR'];
        $this->assertEquals('1'.self::$frenchNarrowSpace.'234'.self::$frenchNarrowSpace.'567,89'.self::$frenchSpace.'€', $formatter->currencyFormat(1234567.89, 'eur', $options));

        $options = ['locale' => 'de_DE'];
        $this->assertEquals('1.234.567,89 €', $formatter->currencyFormat(1234567.89, 'eur', $options));
    }

    public function testCurrencyFormatIndianStyle(): void
    {
        $formatter = MoneyFormatter::get();
        $options = ['locale' => 'en_IN'];
        if (version_compare(self::$icuVersion, '56', '>')) {
            $this->assertEquals('₹1,00,000.12', $formatter->currencyFormat(100000.12, 'inr', $options));
        } else {
            $this->assertEquals('₹'.self::$frenchSpace.'1,00,000.12', $formatter->currencyFormat(100000.12, 'inr', $options));
        }
    }

    public function testCurrencyFormatHtml(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals('-$100.00', $formatter->currencyFormatHtml(-100, 'usd'));
        $this->assertEquals('$1,000.24', $formatter->currencyFormatHtml(1000.24, 'usd'));
        $this->assertEquals('¥1,000', $formatter->currencyFormatHtml(1000.24, 'jpy'));
        if (version_compare(self::$icuVersion, '56', '>')) {
            $this->assertEquals('BTC 1,000.12345678', $formatter->currencyFormatHtml(1000.12345678, 'btc'));
        } else {
            $this->assertEquals('BTC1,000.12345678', $formatter->currencyFormatHtml(1000.12345678, 'btc'));
        }
    }

    public function testCurrencyFormatHtmlPrecision(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals('-$100.0000', $formatter->currencyFormatHtml(-100, 'usd', ['precision' => 4]));
        $this->assertEquals('$1,000.2400', $formatter->currencyFormatHtml(1000.24, 'usd', ['precision' => 4]));
        $this->assertEquals('¥1,000.2400', $formatter->currencyFormatHtml(1000.24, 'jpy', ['precision' => 4]));
        if (version_compare(self::$icuVersion, '56', '>')) {
            $this->assertEquals('BTC 1,000.1235', $formatter->currencyFormatHtml(1000.12345678, 'btc', ['precision' => 4]));
        } else {
            $this->assertEquals('BTC1,000.12345678', $formatter->currencyFormatHtml(1000.12345678, 'btc'));
        }
    }

    public function testCurrencyFormatHtmlCurrencyCode(): void
    {
        $formatter = MoneyFormatter::get();

        if (version_compare(self::$icuVersion, '56', '>')) {
            $this->assertEquals('USD 1,000.24', $formatter->currencyFormatHtml(1000.24, 'usd', ['use_symbol' => false]));
        } else {
            $this->assertEquals('USD1,000.24', $formatter->currencyFormatHtml(1000.24, 'usd', ['use_symbol' => false]));
        }
    }

    public function testFormat(): void
    {
        $formatter = MoneyFormatter::get();

        $money = new Money('usd', 10000);
        $this->assertEquals('$100.00', $formatter->format($money));
    }

    public function testNormalize(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals(105, $formatter->normalizeToZeroDecimal('usd', 1.052));

        $this->assertEquals(1, $formatter->normalizeToZeroDecimal('xpf', 1.052));
    }

    public function testDenormalize(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals(1.05, $formatter->denormalizeFromZeroDecimal('USD', 105));

        $this->assertEquals(1, $formatter->denormalizeFromZeroDecimal('BIF', 1));
    }

    public function testRound(): void
    {
        $formatter = MoneyFormatter::get();

        $this->assertEquals(1.05, $formatter->round('usd', 1.051684));

        $this->assertEquals(1, $formatter->round('jpy', 1.151));

        $this->assertEquals(1.12345679, $formatter->round('btc', 1.123456789));
    }
}
