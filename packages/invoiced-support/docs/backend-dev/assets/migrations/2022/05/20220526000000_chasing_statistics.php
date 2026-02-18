<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChasingStatistics extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ChasingStatistics');
        $this->addTenant($table);
        $table
            ->addColumn('date', 'date', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('type', 'smallinteger', ['default' => 1])
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('cadence_id', 'integer')
            ->addColumn('cadence_step_id', 'integer')
            ->addColumn('channel', 'smallinteger')
            ->addColumn('paid', 'date', ['null' => true, 'default' => null])
            ->addColumn('payment_responsible', 'boolean', ['null' => true, 'default' => null])
            ->addIndex(['tenant_id', 'date'])
            ->addIndex(['cadence_id', 'date'])
            ->create();
    }
}
