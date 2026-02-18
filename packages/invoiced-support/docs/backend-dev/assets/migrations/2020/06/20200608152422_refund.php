<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Refund extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Refunds');
        $this->addTenant($table);
        $table->addColumn('charge_id', 'integer')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('status', 'string')
            ->addColumn('failure_message', 'string', ['null' => true, 'default' => null])
            ->addColumn('gateway', 'string')
            ->addColumn('gateway_id', 'string', ['collation' => 'utf8_bin'])
            ->addTimestamps()
            ->addIndex(['tenant_id', 'gateway', 'gateway_id'])
            ->addForeignKey('charge_id', 'Charges', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
