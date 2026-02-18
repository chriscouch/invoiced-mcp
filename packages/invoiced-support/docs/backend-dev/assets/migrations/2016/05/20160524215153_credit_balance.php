<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CreditBalance extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CreditBalances', ['id' => false, 'primary_key' => ['transaction_id']]);
        $this->addTenant($table);
        $table->addColumn('transaction_id', 'integer')
            ->addColumn('customer_id', 'integer')
            ->addColumn('timestamp', 'integer')
            ->addColumn('balance', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addForeignKey('transaction_id', 'Transactions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('currency')
            ->addIndex(['timestamp'])
            ->create();
    }
}
