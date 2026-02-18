<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class WePayRbitDataMccColumn extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('WePayRbitData')
            ->addColumn('mcc', 'string')
            ->update();
    }
}
