<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomField extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CustomFields', ['id' => false, 'primary_key' => ['tenant_id', 'id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string', ['length' => 40])
            ->addColumn('name', 'string')
            ->addColumn('type', 'enum', ['values' => ['string', 'boolean', 'double', 'enum', 'date', 'money'], 'default' => 'string'])
            ->addColumn('object', 'enum', ['null' => true, 'default' => null, 'values' => ['customer', 'invoice', 'credit_note', 'estimate', 'line_item', 'subscription', 'transaction']])
            ->addColumn('choices', 'text')
            ->addColumn('external', 'boolean', ['default' => true])
            ->addTimestamps()
            ->create();
    }
}
