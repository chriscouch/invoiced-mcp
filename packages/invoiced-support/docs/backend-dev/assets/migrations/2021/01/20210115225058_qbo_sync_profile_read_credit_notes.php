<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QboSyncProfileReadCreditNotes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('read_credit_notes', 'boolean', ['default' => false])
            ->update();
    }
}
