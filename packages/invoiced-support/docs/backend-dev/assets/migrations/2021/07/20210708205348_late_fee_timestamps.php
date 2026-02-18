<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LateFeeTimestamps extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('LateFeeSchedules')
            ->addTimestamps()
            ->update();
    }
}
