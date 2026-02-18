<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QuickBooksOnlineSyncProfile extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('QuickBooksOnlineSyncProfiles', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('auto_sync', 'boolean')
            ->addColumn('auto_import', 'boolean')
            ->addColumn('last_synced', 'integer', ['default' => null, 'null' => true])
            ->addColumn('discount_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('tax_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('undeposited_funds_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('namespace_customers', 'boolean')
            ->addColumn('namespace_invoices', 'boolean')
            ->addColumn('namespace_items', 'boolean')
            ->addColumn('invoice_start_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('custom_field_1', 'string', ['null' => true, 'default' => null])
            ->addColumn('custom_field_2', 'string', ['null' => true, 'default' => null])
            ->addColumn('custom_field_3', 'string', ['null' => true, 'default' => null])
            ->addColumn('import_invoices', 'boolean')
            ->addColumn('import_payments', 'boolean')
            ->addColumn('import_pdfs', 'boolean')
            ->addColumn('import_invoices_as_drafts', 'boolean')
            ->addColumn('next_sync', 'integer', ['null' => true, 'default' => null])
            ->addColumn('next_import', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('auto_sync')
            ->addIndex('next_sync')
            ->addIndex('next_import')
            ->create();
    }
}
