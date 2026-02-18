<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DropEstimateNumberIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Estimates')
            ->removeIndex('number')
            ->update();
    }
}
