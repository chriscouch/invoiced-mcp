<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LateFeeScheduleLastRun extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('LateFeeSchedules')
            ->addColumn('last_run', 'datetime', ['null' => true, 'default' => null])
            ->changeColumn('start_date', 'date')
            ->update();
    }
}
