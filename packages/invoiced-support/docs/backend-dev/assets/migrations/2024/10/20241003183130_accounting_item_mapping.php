<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingItemMapping extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AccountingItemMappings', ['id' => false, 'primary_key' => ['item_id']]);
        $this->addTenant($table);
        $table->addColumn('item_id', 'integer')
            ->addColumn('integration_id', 'integer', ['length' => 3, 'signed' => false])
            ->addColumn('accounting_id', 'string')
            ->addColumn('source', 'enum', ['values' => ['accounting_system', 'invoiced']])
            ->addIndex(['integration_id', 'accounting_id'])
            ->addTimestamps()
            ->addForeignKey('item_id', 'CatalogItems', 'internal_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
