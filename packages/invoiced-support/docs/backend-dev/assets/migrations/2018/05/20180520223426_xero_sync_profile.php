<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroSyncProfile extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('XeroSyncProfiles', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('auto_sync', 'boolean')
            ->addColumn('auto_import', 'boolean')
            ->addColumn('last_synced', 'integer', ['default' => null, 'null' => true])
            ->addColumn('item_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('tax_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('tax_type', 'string', ['null' => true, 'default' => null])
            ->addColumn('undeposited_funds_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('tax_inclusive', 'boolean')
            ->addColumn('add_tax_line_item', 'boolean')
            ->addColumn('send_item_code', 'boolean')
            ->addColumn('invoice_start_date', 'integer', ['null' => true, 'default' => null])
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
