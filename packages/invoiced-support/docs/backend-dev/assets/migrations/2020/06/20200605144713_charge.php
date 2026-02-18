<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Charge extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Charges');
        $this->addTenant($table);
        $table->addColumn('payment_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('status', 'enum', ['values' => ['succeeded', 'pending', 'failed'], 'default' => 'pending'])
            ->addColumn('amount_refunded', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('refunded', 'boolean', ['default' => false])
            ->addColumn('disputed', 'boolean', ['default' => false])
            ->addColumn('receipt_email', 'string', ['null' => true, 'default' => null])
            ->addColumn('failure_message', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_source_type', 'integer', ['length' => 1, 'null' => true, 'default' => null])
            ->addColumn('payment_source_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('gateway', 'string')
            ->addColumn('gateway_id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('last_status_check', 'integer')
            ->addTimestamps()
            ->addIndex(['tenant_id', 'gateway', 'gateway_id'])
            ->addForeignKey('payment_id', 'Payments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        $this->table('Payments')
            ->addColumn('charge_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('charge_id', 'Charges', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
