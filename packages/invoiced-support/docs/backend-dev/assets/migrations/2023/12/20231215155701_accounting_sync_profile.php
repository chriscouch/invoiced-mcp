<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingSyncProfile extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AccountingSyncProfiles');
        $this->addTenant($table);
        $table->addColumn('integration', 'tinyinteger')
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
            ->addColumn('last_synced', 'integer', ['null' => true, 'default' => null])
            ->addColumn('invoice_start_date', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex(['tenant_id', 'integration'])
            ->create();
    }
}
