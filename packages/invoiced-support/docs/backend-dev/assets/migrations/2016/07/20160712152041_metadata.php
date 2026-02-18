<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Metadata extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Metadata', ['id' => false, 'primary_key' => ['tenant_id', 'object_type', 'object_id', 'key']]);
        $this->addTenant($table);
        $table->addColumn('object_type', 'enum', ['values' => ['coupon', 'tax_rate', 'catalog_item', 'customer', 'credit_note', 'estimate', 'invoice', 'line_item', 'transaction', 'plan', 'subscription']])
            ->addColumn('object_id', 'integer')
            ->addColumn('key', 'string', ['length' => 40])
            ->addColumn('value', 'string')
            ->addIndex('value')
            ->create();
    }
}
