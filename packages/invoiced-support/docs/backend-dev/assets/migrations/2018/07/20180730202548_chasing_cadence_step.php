<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChasingCadenceStep extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ChasingCadenceSteps');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('chasing_cadence_id', 'integer')
            ->addColumn('action', 'string', ['length' => 8])
            ->addColumn('schedule', 'string')
            ->addColumn('email_template_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('assigned_user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('order', 'integer')
            ->addTimestamps()
            ->addForeignKey('chasing_cadence_id', 'ChasingCadences', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('assigned_user_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addIndex('order')
            ->create();

        $this->table('Customers')
            ->addColumn('chasing_cadence_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('next_chase_step_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('chasing_cadence_id', 'ChasingCadences', 'id', ['update' => 'CASCADE', 'delete' => 'SET NULL'])
            ->addForeignKey('next_chase_step_id', 'ChasingCadenceSteps', 'id', ['update' => 'CASCADE', 'delete' => 'SET NULL'])
            ->update();
    }
}
