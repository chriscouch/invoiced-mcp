<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MigrateNetSuiteSyncProfiles extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('INSERT IGNORE INTO AccountingSyncProfiles (tenant_id, integration, read_customers, write_customers, read_invoices, read_invoices_as_drafts, read_pdfs, write_invoices, read_credit_notes, write_credit_notes, read_payments, write_payments, write_convenience_fees, payment_accounts, read_cursor, last_synced, invoice_start_date, created_at, updated_at) SELECT tenant_id, 2 AS integration, read_customers, write_customers, read_invoices, read_invoices_as_drafts, read_pdfs, write_invoices, read_credit_notes, write_credit_notes, read_payments, write_payments, write_convenience_fees, payment_accounts, read_cursor, last_synced, invoice_start_date, created_at, updated_at FROM NetSuiteSyncProfiles');
    }
}
