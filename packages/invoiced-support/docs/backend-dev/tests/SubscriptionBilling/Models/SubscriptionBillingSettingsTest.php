<?php

namespace App\Tests\SubscriptionBilling\Models;

use App\Tests\AppTestCase;

class SubscriptionBillingSettingsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testToArray(): void
    {
        $expected = [
            'after_subscription_nonpayment' => 'do_nothing',
            'subscription_draft_invoices' => false,
        ];

        $this->assertEquals($expected, self::$company->subscription_billing_settings->toArray());
    }

    public function testEdit(): void
    {
        self::$company->subscription_billing_settings->subscription_draft_invoices = true;
        $this->assertTrue(self::$company->subscription_billing_settings->save());
    }

    public function testDelete(): void
    {
        $this->assertFalse(self::$company->subscription_billing_settings->delete());
    }
}
