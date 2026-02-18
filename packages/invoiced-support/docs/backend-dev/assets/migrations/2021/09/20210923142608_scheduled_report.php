<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ScheduledReport extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ScheduledReports');
        $this->addTenant($table);
        $table->addColumn('saved_report_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addColumn('parameters', 'text')
            ->addColumn('time_of_day', 'integer')
            ->addColumn('last_run', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('next_run', 'timestamp')
            ->addColumn('frequency', 'enum', ['values' => ['day_of_week', 'day_of_month'], 'default' => 'day_of_week'])
            ->addColumn('run_date', 'integer')
            ->addTimestamps()
            ->addForeignKey('saved_report_id', 'SavedReports', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('member_id', 'Members', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->create();
    }
}
