<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QboSyncProfileWriteCreditNotes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('write_credit_notes', 'boolean', ['default' => false])
            ->update();
    }
}
