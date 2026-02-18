<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingCustomerMapping extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AccountingCustomerMappings', ['id' => false, 'primary_key' => ['customer_id']]);
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('integration_id', 'integer', ['length' => 3, 'signed' => false])
            ->addColumn('accounting_id', 'string')
            ->addColumn('source', 'enum', ['values' => ['accounting_system', 'invoiced']])
            ->addIndex(['integration_id', 'accounting_id'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
