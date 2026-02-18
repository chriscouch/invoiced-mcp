<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CollectionTask extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Tasks');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('name', 'string')
            ->addColumn('action', 'string', ['length' => 8])
            ->addColumn('due_date', 'integer')
            ->addColumn('complete', 'boolean')
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('completed_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('completed_by_user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('chase_step_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('action')
            ->addIndex('complete')
            ->addForeignKey('chase_step_id', 'ChasingCadenceSteps', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('completed_by_user_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
