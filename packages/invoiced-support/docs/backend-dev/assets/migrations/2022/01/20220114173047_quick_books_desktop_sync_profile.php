<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QuickBooksDesktopSyncProfile extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();
        $qbdSyncProfiles = $this->table('QuickBooksDesktopSyncProfiles', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($qbdSyncProfiles);
        $qbdSyncProfiles
            ->addColumn('read_customers', 'boolean')
            ->addColumn('read_credit_notes', 'boolean')
            ->addColumn('read_invoices', 'boolean')
            ->addColumn('read_payments', 'boolean')
            ->addColumn('write_payments', 'boolean')
            ->addColumn('invoice_start_date', 'date', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();

        $this->execute("INSERT INTO QuickBooksDesktopSyncProfiles (tenant_id, read_customers, read_credit_notes, read_invoices, read_payments, write_payments) SELECT tenant_id, true, true, true, true, true FROM Settings WHERE accounting_system = 'quickbooks_desktop'");
    }
}
