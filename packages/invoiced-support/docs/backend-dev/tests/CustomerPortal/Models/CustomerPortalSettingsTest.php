<?php

namespace App\Tests\CustomerPortal\Models;

use App\Tests\AppTestCase;

class CustomerPortalSettingsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testToArray(): void
    {
        $expected = [
            'allow_advance_payments' => false,
            'allow_autopay_enrollment' => false,
            'allow_billing_portal_cancellations' => false,
            'allow_billing_portal_profile_changes' => true,
            'allow_editing_contacts' => true,
            'allow_invoice_disputes' => true,
            'allow_invoice_payment_selector' => false,
            'allow_partial_payments' => true,
            'billing_portal_show_company_name' => true,
            'customer_portal_auth_url' => null,
            'enabled' => true,
            'google_analytics_id' => '',
            'include_sub_customers' => true,
            'invoice_payment_to_item_selection' => true,
            'require_authentication' => false,
            'show_powered_by' => true,
            'welcome_message' => '',
        ];

        $this->assertEquals($expected, self::$company->customer_portal_settings->toArray());
    }

    public function testEdit(): void
    {
        self::$company->customer_portal_settings->allow_billing_portal_profile_changes = false;
        $this->assertTrue(self::$company->customer_portal_settings->save());
    }

    public function testDelete(): void
    {
        $this->assertFalse(self::$company->customer_portal_settings->delete());
    }
}
