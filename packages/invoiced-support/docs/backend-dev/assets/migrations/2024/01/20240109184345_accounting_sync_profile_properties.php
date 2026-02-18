<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingSyncProfileProperties extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('NetSuiteSyncProfiles')
            ->addColumn('read_customers', 'boolean')
            ->addColumn('write_customers', 'boolean')
            ->addColumn('read_invoices', 'boolean')
            ->addColumn('read_invoices_as_drafts', 'boolean')
            ->addColumn('read_pdfs', 'boolean')
            ->addColumn('write_invoices', 'boolean')
            ->addColumn('read_credit_notes', 'boolean')
            ->addColumn('write_credit_notes', 'boolean')
            ->addColumn('read_payments', 'boolean')
            ->addColumn('write_payments', 'boolean')
            ->addColumn('write_convenience_fees', 'boolean')
            ->addColumn('payment_accounts', 'text')
            ->addColumn('read_cursor', 'integer', ['null' => true, 'default' => null])
            ->removeColumn('auto_sync')
            ->removeColumn('auto_import')
            ->removeColumn('payment_start_date')
            ->removeColumn('invoice_custom_field_import_mapping')
            ->removeColumn('line_item_custom_field_import_mapping')
            ->removeColumn('next_sync')
            ->removeColumn('next_import')
            ->update();

        $this->table('QuickBooksDesktopSyncProfiles')
            ->addColumn('write_customers', 'boolean')
            ->addColumn('read_invoices_as_drafts', 'boolean')
            ->addColumn('read_pdfs', 'boolean')
            ->addColumn('write_invoices', 'boolean')
            ->addColumn('write_credit_notes', 'boolean')
            ->addColumn('write_convenience_fees', 'boolean')
            ->addColumn('payment_accounts', 'text')
            ->addColumn('read_cursor', 'integer', ['null' => true, 'default' => null])
            ->addColumn('last_synced', 'integer', ['null' => true, 'default' => null])
            ->update();

        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('read_invoices', 'boolean')
            ->addColumn('read_invoices_as_drafts', 'boolean')
            ->addColumn('read_pdfs', 'boolean')
            ->addColumn('read_payments', 'boolean')
            ->update();

        $this->table('XeroSyncProfiles')
            ->addColumn('read_invoices', 'boolean')
            ->addColumn('read_invoices_as_drafts', 'boolean')
            ->addColumn('read_pdfs', 'boolean')
            ->addColumn('read_payments', 'boolean')
            ->update();
    }
}
