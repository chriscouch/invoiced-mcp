<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QboSyncProfileReadCursor extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('read_cursor', 'integer', ['null' => true, 'default' => null])
            ->update();

        $this->execute('UPDATE QuickBooksOnlineSyncProfiles SET read_cursor=last_synced');
    }
}
