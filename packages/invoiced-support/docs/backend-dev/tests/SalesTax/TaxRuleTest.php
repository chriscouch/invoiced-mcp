<?php

namespace App\Tests\SalesTax;

use App\SalesTax\Models\TaxRule;
use App\Tests\AppTestCase;

class TaxRuleTest extends AppTestCase
{
    private static TaxRule $rule;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCreateInvalidCountry(): void
    {
        $rule = new TaxRule();
        $rule->country = 'not a country';

        $this->assertFalse($rule->save());
        $this->assertEquals('Country is invalid', $rule->getErrors()[0]['message']);
    }

    public function testCreate(): void
    {
        self::$rule = new TaxRule();
        self::$rule->tax_rate = 'gst';
        $this->assertTrue(self::$rule->save());

        $this->assertEquals(self::$company->id(), self::$rule->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $rules = TaxRule::all();

        $this->assertCount(1, $rules);
        $this->assertEquals(self::$rule->id(), $rules[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$rule->id(),
            'tax_rate' => 'gst',
            'state' => null,
            'country' => null,
            'created_at' => self::$rule->created_at,
            'updated_at' => self::$rule->updated_at,
        ];

        $this->assertEquals($expected, self::$rule->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$rule->country = 'US';
        $this->assertTrue(self::$rule->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$rule->delete());
    }
}
