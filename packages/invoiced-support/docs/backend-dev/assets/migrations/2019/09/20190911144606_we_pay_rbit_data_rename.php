<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class WePayRbitDataRename extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('WePayRbitData')
            ->rename('WePayData')
            ->update();
    }
}
