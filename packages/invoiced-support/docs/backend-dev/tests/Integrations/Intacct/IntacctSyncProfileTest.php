<?php

namespace App\Tests\Integrations\Intacct;

use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class IntacctSyncProfileTest extends AppTestCase
{
    private static IntacctSyncProfile $profile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$profile = new IntacctSyncProfile();
        $this->assertTrue(self::$profile->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'integration_version' => 3,
            'read_customers' => false,
            'write_customers' => false,
            'read_invoices' => false,
            'read_invoices_as_drafts' => false,
            'write_invoices' => false,
            'read_payments' => false,
            'write_payments' => false,
            'read_ar_adjustments' => false,
            'read_credit_notes' => false,
            'write_credit_notes' => false,
            'write_convenience_fees' => false,
            'read_pdfs' => true,
            'bad_debt_account' => null,
            'created_at' => self::$profile->created_at,
            'updated_at' => self::$profile->updated_at,
            'customer_import_type' => 'customer',
            'customer_top_level' => true,
            'credit_note_types' => [],
            'invoice_types' => [],
            'invoice_import_mapping' => null,
            'invoice_start_date' => CarbonImmutable::now()->getTimestamp(),
            'write_to_order_entry' => false,
            'item_account' => null,
            'convenience_fee_account' => null,
            'item_department_id' => null,
            'item_location_id' => null,
            'map_catalog_item_to_item_id' => false,
            'last_synced' => null,
            'line_item_import_mapping' => null,
            'ship_to_invoice_distribution_list' => false,
            'payment_accounts' => [],
            'undeposited_funds_account' => null,
            'customer_read_query_addon' => null,
            'ar_adjustment_read_query_addon' => null,
            'invoice_import_query_addon' => null,
            'invoice_location_id_filter' => null,
            'customer_custom_field_mapping' => null,
            'invoice_custom_field_mapping' => null,
            'line_item_custom_field_mapping' => null,
            'payment_plan_import_settings' => null,
            'overpayment_department_id' => null,
            'overpayment_location_id' => null,
            'read_batch_size' => null,
        ];

        $this->assertEquals($expected, self::$profile->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testUpgradeV3(): void
    {
        self::$profile->integration_version = 3;
        $this->assertTrue(self::$profile->save());
        $this->assertGreaterThan(0, self::$profile->read_cursor);
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$profile->delete());
    }
}
