<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QboSyncProfileWritePayments extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('write_payments', 'boolean', ['default' => true])
            ->update();
    }
}
