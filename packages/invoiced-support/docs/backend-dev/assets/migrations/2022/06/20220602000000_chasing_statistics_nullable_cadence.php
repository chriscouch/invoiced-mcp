<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChasingStatisticsNullableCadence extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ChasingStatistics')
            ->changeColumn('cadence_id', 'integer', ['null' => true, 'default' => null])
            ->changeColumn('cadence_step_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
