<?php

namespace App\Tests\Core\Billing\Models;

use App\Core\Billing\Models\BillingProfile;
use App\Tests\AppTestCase;

class BillingProfileTest extends AppTestCase
{
    private static BillingProfile $billingProfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::getService('test.database')->executeQuery('DELETE FROM BillingProfiles WHERE invoiced_customer="billing_profile_test"');
    }

    public function testCreate(): void
    {
        self::$billingProfile = new BillingProfile();
        self::$billingProfile->name = 'Test';
        self::$billingProfile->billing_system = 'invoiced';
        self::$billingProfile->invoiced_customer = 'billing_profile_test';
        $this->assertTrue(self::$billingProfile->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$billingProfile->past_due = true;
        $this->assertTrue(self::$billingProfile->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$billingProfile->id(),
            'name' => 'Test',
            'past_due' => true,
            'created_at' => self::$billingProfile->created_at,
            'updated_at' => self::$billingProfile->updated_at,
        ];

        $this->assertEquals($expected, self::$billingProfile->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertFalse(self::$billingProfile->delete());
    }

    public function testGetOrCreate(): void
    {
        $billingProfile = BillingProfile::getOrCreate(self::$company);
        $this->assertEquals($billingProfile->id(), self::$company->billing_profile?->id());

        $billingProfile2 = BillingProfile::getOrCreate(self::$company);
        $this->assertEquals($billingProfile->id(), $billingProfile2->id());
    }
}
