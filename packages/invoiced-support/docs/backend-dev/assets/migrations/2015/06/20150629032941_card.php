<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Card extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Cards');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('funding', 'string', ['length' => 30])
            ->addColumn('brand', 'string', ['length' => 30])
            ->addColumn('last4', 'string', ['length' => 4])
            ->addColumn('exp_month', 'integer', ['length' => 2])
            ->addColumn('exp_year', 'integer', ['length' => 4])
            ->addColumn('gateway', 'string', ['default' => 'stripe'])
            ->addColumn('gateway_customer', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('gateway_id', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('chargeable', 'boolean')
            ->addColumn('merchant_account_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('failure_reason', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('gateway_customer')
            ->addIndex('gateway_id')
            ->addIndex('merchant_account_id')
            ->addIndex('chargeable')
            ->create();
    }
}
