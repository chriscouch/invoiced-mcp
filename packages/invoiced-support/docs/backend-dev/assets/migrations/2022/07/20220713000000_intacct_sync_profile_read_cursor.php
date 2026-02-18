<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSyncProfileReadCursor extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('read_cursor', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
