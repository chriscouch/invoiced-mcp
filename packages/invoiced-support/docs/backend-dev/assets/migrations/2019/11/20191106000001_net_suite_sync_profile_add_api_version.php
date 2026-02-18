<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NetSuiteSyncProfileAddApiVersion extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('NetSuiteSyncProfiles')
            ->addColumn('integration_version', 'integer', ['default' => 2])
            ->update();
    }
}
