<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CompletedChasingSteps extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CompletedChasingSteps');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('cadence_id', 'integer')
            ->addColumn('chase_step_id', 'integer')
            ->addColumn('timestamp', 'integer')
            ->addColumn('successful', 'boolean')
            ->addColumn('message', 'string', ['null' => true, 'default' => null])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('cadence_id', 'ChasingCadences', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('chase_step_id', 'ChasingCadenceSteps', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('timestamp')
            ->create();
    }
}
