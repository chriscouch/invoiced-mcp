<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MigrateAccountingSyncProfileProperties extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE QuickBooksOnlineSyncProfiles SET read_invoices=import_invoices, read_invoices_as_drafts=import_invoices_as_drafts, read_pdfs=import_pdfs, read_payments=import_payments');
        $this->execute('UPDATE XeroSyncProfiles SET read_invoices=import_invoices, read_invoices_as_drafts=import_invoices_as_drafts, read_pdfs=import_pdfs, read_payments=import_payments');
    }
}
