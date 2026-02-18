<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QboSyncProfileWriteCustomers extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('write_customers', 'boolean', ['default' => true])
            ->update();
    }
}
