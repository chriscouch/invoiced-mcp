<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Settings extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Settings', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('chase_new_invoices', 'boolean')
            ->addColumn('payment_terms', 'string', ['length' => 20, 'null' => true, 'default' => null])
            ->addColumn('payment_retry_schedule', 'string', ['default' => '[3,5,7]'])
            ->addColumn('billing_portal_show_company_name', 'boolean', ['default' => true])
            ->addColumn('allow_billing_portal_cancellations', 'boolean', ['default' => true])
            ->addColumn('default_collection_mode', 'enum', ['values' => ['auto', 'manual'], 'default' => 'manual'])
            ->addColumn('transactions_inherit_invoice_metadata', 'boolean')
            ->addColumn('after_subscription_nonpayment', 'enum', ['values' => ['cancel', 'do_nothing'], 'default' => 'cancel'])
            ->addColumn('allow_autopay_enrollment', 'boolean')
            ->addColumn('allow_partial_payments', 'boolean', ['default' => true])
            ->addColumn('allow_late_fees', 'boolean')
            ->addColumn('late_fee_value', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('late_fee_is_percent', 'boolean')
            ->addColumn('late_fee_grace', 'integer')
            ->addColumn('late_fee_recurring_interval', 'string', ['length' => 5, 'default' => 'day'])
            ->addColumn('late_fee_recurring_count', 'integer', ['length' => 5])
            ->addColumn('tos_url', 'string', ['null' => true, 'default' => null])
            ->addColumn('invoice_overage_price', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('customer_overage_price', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('billing_portal_login_scheme', 'string', ['default' => 'email'])
            ->addColumn('allow_billing_portal_profile_changes', 'boolean', ['default' => true])
            ->addColumn('google_analytics_id', 'string', ['length' => 30])
            ->addColumn('aging_buckets', 'string', ['default' => '[0,7,14,30,60]'])
            ->addColumn('aging_date', 'enum', ['values' => ['date', 'due_date'], 'default' => 'date'])
            ->addColumn('default_template_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('default_theme_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('allow_chasing', 'boolean')
            ->addColumn('chase_schedule', 'text')
            ->addColumn('auto_apply_credits', 'boolean')
            ->addColumn('autopay_delay_days', 'integer', ['default' => 0])
            ->addColumn('debit_cards_only', 'boolean')
            ->addColumn('email_provider', 'enum', ['values' => ['invoiced', 'smtp'], 'default' => 'invoiced'])
            ->addColumn('bcc', 'string')
            ->addColumn('accounting_system', 'string', ['length' => 20, 'null' => true, 'default' => null])
            ->addColumn('add_payment_plan_on_import', 'string', ['null' => true, 'default' => null])
            ->addColumn('tax_calculator', 'enum', ['default' => 'invoiced', 'values' => ['invoiced', 'avalara']])
            ->addColumn('vat_id_validation', 'boolean')
            ->addColumn('allow_vat_validation_errors', 'boolean')
            ->addColumn('unit_cost_precision', 'integer', ['length' => 1, 'null' => true, 'default' => null])
            ->addColumn('allow_invoice_payment_selector', 'boolean')
            ->addColumn('saved_cards_require_cvc', 'boolean')
            ->addColumn('allow_csv_invoice_downloads', 'boolean')
            ->addTimestamps()
            ->addIndex('allow_late_fees')
            ->addIndex('accounting_system')
            ->create();
    }
}
