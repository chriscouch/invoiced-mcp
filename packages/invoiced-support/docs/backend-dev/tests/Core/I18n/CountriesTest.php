<?php

namespace App\Tests\Core\I18n;

use App\Core\I18n\Countries;
use App\Tests\AppTestCase;

class CountriesTest extends AppTestCase
{
    public function testAll(): void
    {
        $countries = new Countries();
        $source = require self::$kernel->getProjectDir().'/assets/countries.php';
        $this->assertEquals($source, $countries->all());
    }

    public function testGetAlpha2(): void
    {
        $countries = new Countries();
        $expected = [
            'code' => 'GB',
            'country' => 'United Kingdom',
            'currency' => 'GBP',
            'phone_code' => '44',
            'tax_id' => [
                'company' => 'VAT Reg No',
                'person' => 'VAT Reg No',
                'government' => 'VAT Reg No',
                'non_profit' => 'VAT Reg No',
            ],
            'seller_has_tax_id' => true,
            'buyer_has_tax_id' => true,
            'alpha3Code' => 'GBR',
            'numeric' => 826,
        ];
        $this->assertEquals($expected, $countries->get('GB'));
        $this->assertEquals($expected, $countries->get('gb'));

        $this->assertNull($countries->get('United States'));
        $this->assertNull($countries->get('WTF'));
    }

    public function testGetAlpha3(): void
    {
        $countries = new Countries();
        $expected = [
            'code' => 'GB',
            'country' => 'United Kingdom',
            'currency' => 'GBP',
            'phone_code' => '44',
            'tax_id' => [
                'company' => 'VAT Reg No',
                'person' => 'VAT Reg No',
                'government' => 'VAT Reg No',
                'non_profit' => 'VAT Reg No',
            ],
            'seller_has_tax_id' => true,
            'buyer_has_tax_id' => true,
            'alpha3Code' => 'GBR',
            'numeric' => 826,
        ];
        $this->assertEquals($expected, $countries->getFromAlpha3('GBR'));
        $this->assertEquals($expected, $countries->getFromAlpha3('gbr'));

        $this->assertNull($countries->get('United States'));
        $this->assertNull($countries->get('WTF'));
    }

    public function testGetNumeric(): void
    {
        $countries = new Countries();
        $expected = [
            'code' => 'GB',
            'country' => 'United Kingdom',
            'currency' => 'GBP',
            'phone_code' => '44',
            'tax_id' => [
                'company' => 'VAT Reg No',
                'person' => 'VAT Reg No',
                'government' => 'VAT Reg No',
                'non_profit' => 'VAT Reg No',
            ],
            'seller_has_tax_id' => true,
            'buyer_has_tax_id' => true,
            'alpha3Code' => 'GBR',
            'numeric' => 826,
        ];
        $this->assertEquals($expected, $countries->getFromNumeric(826));

        $this->assertNull($countries->getFromNumeric(0));
    }

    public function testGetFromName(): void
    {
        $countries = new Countries();
        $this->assertNull($countries->getFromName('GB'));

        $this->assertEquals($countries->get('US'), $countries->getFromName('United States'));
        $this->assertEquals($countries->get('US'), $countries->getFromName('UNITED STATES'));
        $this->assertEquals($countries->get('CA'), $countries->getFromName('Canada'));
        $this->assertEquals($countries->get('GB'), $countries->getFromName('United kingdom'));
        $this->assertNull($countries->getFromName('WTF'));
    }

    public function testExists(): void
    {
        $countries = new Countries();
        $this->assertTrue($countries->exists('US'));
        $this->assertFalse($countries->exists('WTF'));
    }
}
