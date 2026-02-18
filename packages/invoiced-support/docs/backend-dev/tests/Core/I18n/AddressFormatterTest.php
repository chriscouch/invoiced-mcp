<?php

namespace App\Tests\Core\I18n;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\AddressFormatter;
use App\Tests\AppTestCase;
use CommerceGuys\Addressing\Address;
use App\Core\Orm\Model;

class AddressFormatterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testFrom(): void
    {
        $af = new AddressFormatter();
        $this->assertEquals($af, $af->setFrom(self::$company));

        $expected = 'TEST
Company
Address
Austin, TX 78701';

        $this->assertEquals($expected, $af->format());

        self::$company->tax_id = '1234';
        self::$company->address_extra = "Website: example.com\nPhone: 1234";
        $expected = 'TEST
Company
Address
Austin, TX 78701
United States
Website: example.com
Phone: 1234';

        $this->assertEquals($expected, $af->format([
            'showCountry' => true,
            'showName' => true, ]));
    }

    public function testTo(): void
    {
        $af = new AddressFormatter();
        $this->assertEquals($af, $af->setFrom(self::$customer));

        $expected = 'Sherlock
Test
Address
Austin, TX 78701';
        $this->assertEquals($expected, $af->format());

        self::$customer->tax_id = '1234';
        self::$customer->address1 = 'Gunnersbury House';
        self::$customer->address2 = '1 Chapel Hill';
        self::$customer->city = 'London';
        self::$customer->state = 'London';
        self::$customer->postal_code = 'A11 B12';
        self::$customer->country = 'GB';
        $expected = 'Sherlock
Gunnersbury House
1 Chapel Hill
London
A11 B12
United Kingdom
VAT Reg No: 1234';

        $this->assertEquals($expected, $af->format([
            'showCountry' => true,
            'showName' => true, ]));
    }

    public function testBuildAddressCustomerMissingCountry(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();

        $af = new AddressFormatter();
        $af->setFrom($customer);
        $address = $af->buildAddress();
        $this->assertEquals('US', $address->getCountryCode());
    }

    public function testBuildAddressWithSpaces(): void
    {
        $customer = new Customer();
        $customer->tenant_id = (int) self::$company->id();
        $customer->name = ' ';
        $customer->address1 = ' ';
        $customer->address2 = ' ';
        $customer->city = ' ';
        $customer->state = ' ';
        $customer->postal_code = ' ';
        $customer->country = ' ';

        $af = new AddressFormatter();
        $af->setFrom($customer);
        $address = $af->buildAddress();
        $this->assertEquals('', $address->getAddressLine1());
        $this->assertEquals('', $address->getAddressLine2());
        $this->assertEquals('US', $address->getCountryCode());
        $this->assertEquals('', $address->getAdministrativeArea());
        $this->assertEquals('', $address->getPostalCode());
        $this->assertEquals('', $address->getLocality());
        $this->assertEquals('', $address->getGivenName());
    }

    public function testBuildAddressCustomerMissingCountryAndCompany(): void
    {
        $customer = new Customer();

        $af = new AddressFormatter();
        $af->setFrom($customer);
        $address = $af->buildAddress();
        $this->assertEquals('US', $address->getCountryCode());
    }

    public function testFormatAddress(): void
    {
        $af = new AddressFormatter();
        $model = \Mockery::mock(Model::class);
        $model->shouldReceive('get')->andReturn([]);
        $af->setFrom($model);
        $address = new Address();
        $this->assertEquals('', $af->formatAddress($address));

        $address = new Address(
            countryCode: 'CA',
            administrativeArea: 'British Columbia',
            locality: 'British Columbia',
            dependentLocality: 'British Columbia',
            postalCode: 'V6C3E2',
            addressLine1: 'Canada Place Business Centre',
            addressLine2: 'World Trade Centre Suite 404- 999 Canada Place',
            organization: 'Trinity Canada'
        );

        $this->assertEquals(
            "Trinity Canada\n".
            "Canada Place Business Centre\n".
            "World Trade Centre Suite 404- 999 Canada Place\n".
            "British Columbia British Columbia V6C3E2\n".
            'Canada',
            $af->formatAddress($address, [
            'showCountry' => true,
        ]));

        $this->assertEquals(
            "Trinity Canada\n".
            "Canada Place Business Centre\n".
            "World Trade Centre Suite 404- 999 Canada Place\n".
            'British Columbia British Columbia V6C3E2',
            $af->formatAddress($address, [
            'showCountry' => false,
        ]));
    }
}
