<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillSettings extends MultitenantModelMigration
{
    private const AR_PROPERTIES = [
        'add_payment_plan_on_import',
        'aging_buckets',
        'aging_date',
        'allow_chasing',
        'auto_apply_credits',
        'autopay_delay_days',
        'bcc',
        'chase_new_invoices',
        'chase_schedule',
        'debit_cards_only',
        'default_collection_mode',
        'default_consolidated_invoicing',
        'default_template_id',
        'default_theme_id',
        'email_provider',
        'payment_retry_schedule',
        'payment_terms',
        'reply_to_inbox_id',
        'saved_cards_require_cvc',
        'tax_calculator',
        'transactions_inherit_invoice_metadata',
        'unit_cost_precision',
    ];

    private const AP_PROPERTIES = [
        'aging_buckets',
        'aging_date',
    ];

    private const CASH_APP_PROPERTIES = [
        'short_pay_units',
        'short_pay_amount',
    ];

    private const CUSTOMER_PORTAL_PROPERTIES = [
        'allow_invoice_payment_selector',
        'allow_partial_payments',
        'allow_advance_payments',
        'allow_autopay_enrollment',
        'allow_billing_portal_cancellations',
        'billing_portal_show_company_name',
        'billing_portal_login_scheme',
        'allow_billing_portal_profile_changes',
        'allow_csv_invoice_downloads',
        'google_analytics_id',
        'customer_portal_auth_url',
    ];

    private const SUBSCRIPTION_BILLING_PROPERTIES = [
        'after_subscription_nonpayment',
        'subscription_draft_invoices',
    ];

    public function up(): void
    {
        $this->execute($this->makeSql('AccountsPayableSettings', self::AP_PROPERTIES, 'accounts_payable'));
        $this->execute($this->makeSql('AccountsReceivableSettings', self::AR_PROPERTIES, 'accounts_receivable'));
        $this->execute($this->makeSql('CashApplicationSettings', self::CASH_APP_PROPERTIES, 'cash_application'));
        $this->execute($this->makeSql('CustomerPortalSettings', self::CUSTOMER_PORTAL_PROPERTIES, 'customer_portal'));
        $this->execute($this->makeSql('SubscriptionBillingSettings', self::SUBSCRIPTION_BILLING_PROPERTIES, 'subscription_billing'));
    }

    private function makeSql(string $tablename, array $columns, string $product): string
    {
        $columnsStr = implode(', ', $columns);
        $updates = [];
        foreach ($columns as $column) {
            $updates[] = "$column=VALUES($column)";
        }
        $updatesStr = implode(', ', $updates);

        return 'INSERT INTO '.$tablename.' (tenant_id, '.$columnsStr.') SELECT tenant_id, '.$columnsStr.' FROM Settings WHERE EXISTS (SELECT 1 FROM Features WHERE tenant_id=Settings.tenant_id AND enabled=1 AND feature="module_'.$product.'") ON DUPLICATE KEY UPDATE '.$updatesStr;
    }
}
