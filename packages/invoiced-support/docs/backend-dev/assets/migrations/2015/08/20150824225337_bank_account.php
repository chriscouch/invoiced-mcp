<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class BankAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('BankAccounts');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('bank_name', 'string', ['length' => 30])
            ->addColumn('last4', 'string', ['length' => 4])
            ->addColumn('routing_number', 'integer', ['length' => 10, 'null' => true, 'default' => null])
            ->addColumn('verified', 'boolean')
            ->addColumn('gateway', 'string', ['default' => 'stripe'])
            ->addColumn('gateway_customer', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('gateway_id', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('country', 'string', ['length' => 2, 'default' => 'US'])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('verification_last_sent', 'integer')
            ->addColumn('chargeable', 'boolean')
            ->addColumn('merchant_account_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('failure_reason', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('chargeable')
            ->addIndex('chargeable')
            ->addIndex('gateway_customer')
            ->addIndex('gateway_id')
            ->addIndex('merchant_account_id')
            ->create();
    }
}
