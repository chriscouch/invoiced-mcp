<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CreateCustomerPortalSettings extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('INSERT IGNORE INTO CustomerPortalSettings (tenant_id, customer_portal_auth_url, allow_invoice_payment_selector, allow_partial_payments, allow_advance_payments, allow_autopay_enrollment, google_analytics_id, allow_csv_invoice_downloads, allow_billing_portal_profile_changes, allow_billing_portal_cancellations, billing_portal_show_company_name, billing_portal_login_scheme) SELECT tenant_id, NULL AS customer_portal_auth_url, 0 AS allow_invoice_payment_selector, 1 AS allow_partial_payments, 0 AS allow_advance_payments, 0 AS allow_autopay_enrollment, NULL AS google_analytics_id, 0 AS allow_csv_invoice_downloads, 1 AS allow_billing_portal_profile_changes, 0 AS allow_billing_portal_cancellations, 1 AS billing_portal_show_company_name, "email" AS billing_portal_login_scheme FROM AccountsReceivableSettings');
    }
}
