<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChasingCadence extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ChasingCadences');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('time_of_day', 'integer')
            ->addColumn('last_run', 'integer')
            ->addColumn('paused', 'boolean')
            ->addColumn('next_run', 'integer', ['null' => true, 'default' => null])
            ->addColumn('frequency', 'enum', ['values' => ['daily', 'day_of_week', 'day_of_month'], 'default' => 'daily'])
            ->addColumn('run_date', 'integer', ['null' => true, 'default' => null])
            ->addColumn('min_balance', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('assignment_mode', 'enum', ['values' => ['none', 'default', 'conditions'], 'default' => 'none'])
            ->addColumn('assignment_conditions', 'text')
            ->addTimestamps()
            ->addIndex('time_of_day')
            ->addIndex('last_run')
            ->addIndex('paused')
            ->addIndex('next_run')
            ->create();
    }
}
