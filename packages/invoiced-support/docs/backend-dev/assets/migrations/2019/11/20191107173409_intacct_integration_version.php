<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctIntegrationVersion extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('integration_version', 'integer', ['default' => 1])
            ->update();
    }
}
