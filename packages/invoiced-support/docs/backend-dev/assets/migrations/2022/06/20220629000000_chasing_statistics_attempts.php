<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChasingStatisticsAttempts extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ChasingStatistics')
            ->addColumn('attempts', 'integer')
            ->update();
    }
}
