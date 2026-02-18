<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSyncProfile extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('IntacctSyncProfiles', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('auto_sync', 'boolean')
            ->addColumn('auto_import', 'boolean')
            ->addColumn('last_synced', 'integer', ['default' => null, 'null' => true])
            ->addColumn('item_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('undeposited_funds_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('bad_debt_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('invoice_start_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('item_location_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('item_department_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('customer_import_type', 'enum', ['values' => ['customer', 'bill_to_contact'], 'default' => 'customer'])
            ->addColumn('invoice_import_mapping', 'text', ['default' => null, 'null' => true])
            ->addColumn('line_item_import_mapping', 'text', ['default' => null, 'null' => true])
            ->addColumn('ship_to_invoice_distribution_list', 'boolean')
            ->addColumn('map_catalog_item_to_item_id', 'boolean')
            ->addColumn('next_sync', 'integer', ['null' => true, 'default' => null])
            ->addColumn('next_import', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('auto_sync')
            ->addIndex('next_sync')
            ->addIndex('next_import')
            ->create();
    }
}
