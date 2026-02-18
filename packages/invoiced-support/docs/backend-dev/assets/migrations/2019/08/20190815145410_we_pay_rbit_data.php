<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class WePayRbitData extends MultitenantModelMigration
{
    public function change()
    {
        $wepayData = $this->table('WePayRbitData');
        $this->addTenant($wepayData);
        $wepayData->addColumn('website', 'string')
            ->addColumn('description', 'string')
            ->addTimestamps()
            ->create();
    }
}
