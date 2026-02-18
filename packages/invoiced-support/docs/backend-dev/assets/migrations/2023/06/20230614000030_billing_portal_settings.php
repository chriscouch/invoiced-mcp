<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillingPortalSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('CustomerPortalSettings', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table
            ->addColumn('customer_portal_auth_url', 'string', ['length' => 1000, 'null' => true])
            ->addColumn('allow_invoice_payment_selector', 'boolean')
            ->addColumn('allow_partial_payments', 'boolean', ['default' => true])
            ->addColumn('allow_advance_payments', 'boolean')
            ->addColumn('allow_autopay_enrollment', 'boolean')
            ->addColumn('google_analytics_id', 'string', ['length' => 30])
            ->addColumn('allow_csv_invoice_downloads', 'boolean')
            ->addColumn('allow_billing_portal_profile_changes', 'boolean', ['default' => true])
            ->addColumn('allow_billing_portal_cancellations', 'boolean', ['default' => true])
            ->addColumn('billing_portal_show_company_name', 'boolean', ['default' => true])
            ->addColumn('billing_portal_login_scheme', 'string', ['default' => 'email'])
            ->addTimestamps()
            ->create();
    }
}
