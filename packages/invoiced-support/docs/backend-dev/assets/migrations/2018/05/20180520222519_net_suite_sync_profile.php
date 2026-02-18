<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NetSuiteSyncProfile extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('NetSuiteSyncProfiles', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('auto_sync', 'boolean')
            ->addColumn('auto_import', 'boolean')
            ->addColumn('last_synced', 'integer', ['default' => null, 'null' => true])
            ->addColumn('invoice_start_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payment_start_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('invoice_custom_field_import_mapping', 'text', ['default' => null, 'null' => true])
            ->addColumn('line_item_custom_field_import_mapping', 'text', ['default' => null, 'null' => true])
            ->addColumn('next_sync', 'integer', ['null' => true, 'default' => null])
            ->addColumn('next_import', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('auto_sync')
            ->addIndex('next_sync')
            ->addIndex('next_import')
            ->create();
    }
}
