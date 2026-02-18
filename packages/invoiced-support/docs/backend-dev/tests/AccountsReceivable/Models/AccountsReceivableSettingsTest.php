<?php

namespace App\Tests\AccountsReceivable\Models;

use App\Tests\AppTestCase;

class AccountsReceivableSettingsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testToArray(): void
    {
        $expected = [
            'add_payment_plan_on_import' => null,
            'aging_buckets' => [0, 8, 15, 31, 61],
            'aging_date' => 'date',
            'allow_chasing' => false,
            'auto_apply_credits' => false,
            'autopay_delay_days' => 0,
            'bcc' => '',
            'chase_new_invoices' => false,
            'chase_schedule' => [],
            'debit_cards_only' => false,
            'default_collection_mode' => 'manual',
            'default_consolidated_invoicing' => false,
            'default_customer_type' => 'company',
            'default_template_id' => null,
            'default_theme_id' => null,
            'email_provider' => 'invoiced',
            'inbox_id' => self::$company->accounts_receivable_settings->inbox_id,
            'payment_retry_schedule' => [3, 5, 7],
            'payment_terms' => null,
            'reply_to_inbox_id' => self::$company->accounts_receivable_settings->inbox_id,
            'saved_cards_require_cvc' => false,
            'tax_calculator' => 'invoiced',
            'transactions_inherit_invoice_metadata' => false,
            'unit_cost_precision' => null,
        ];

        $this->assertEquals($expected, self::$company->accounts_receivable_settings->toArray());
    }

    public function testEdit(): void
    {
        self::$company->accounts_receivable_settings->chase_new_invoices = true;
        $this->assertTrue(self::$company->accounts_receivable_settings->save());
    }

    public function testDelete(): void
    {
        $this->assertFalse(self::$company->accounts_receivable_settings->delete());
    }
}
