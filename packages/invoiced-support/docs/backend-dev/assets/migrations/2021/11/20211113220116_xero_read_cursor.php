<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroReadCursor extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroSyncProfiles')
            ->addColumn('read_cursor', 'integer', ['null' => true, 'default' => null])
            ->update();

        $this->execute('UPDATE XeroSyncProfiles SET read_cursor=last_synced');
    }
}
