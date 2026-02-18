<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroV2 extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroSyncProfiles')
            ->addColumn('integration_version', 'smallinteger')
            ->update();
        $this->execute('UPDATE XeroSyncProfiles SET integration_version=1');
    }
}
