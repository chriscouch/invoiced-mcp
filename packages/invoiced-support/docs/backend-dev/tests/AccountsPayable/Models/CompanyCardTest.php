<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Models\CompanyCard;
use App\Tests\AppTestCase;

class CompanyCardTest extends AppTestCase
{
    protected static CompanyCard $companyCard;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$companyCard = new CompanyCard();
        self::$companyCard->funding = 'credit';
        self::$companyCard->brand = 'Visa';
        self::$companyCard->last4 = '1234';
        self::$companyCard->exp_month = 2;
        self::$companyCard->exp_year = 2036;
        self::$companyCard->issuing_country = 'US';
        self::$companyCard->gateway = 'stripe';
        self::$companyCard->stripe_customer = 'cust_test';
        self::$companyCard->stripe_payment_method = 'card_test';
        $this->assertTrue(self::$companyCard->create());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'brand' => 'Visa',
            'created_at' => self::$companyCard->created_at,
            'deleted' => null,
            'deleted_at' => null,
            'exp_month' => 2,
            'exp_year' => 2036,
            'funding' => 'credit',
            'id' => self::$companyCard->id(),
            'issuing_country' => 'US',
            'last4' => 1234,
            'updated_at' => self::$companyCard->updated_at,
        ];
        $this->assertEquals($expected, self::$companyCard->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$companyCard->exp_month = 5;
        $this->assertTrue(self::$companyCard->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$companyCard->delete());
        $this->assertTrue(self::$companyCard->deleted);
        $this->assertTrue(self::$companyCard->persisted());
    }
}
