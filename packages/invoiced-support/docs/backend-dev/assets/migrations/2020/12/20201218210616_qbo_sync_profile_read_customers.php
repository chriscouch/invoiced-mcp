<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QboSyncProfileReadCustomers extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('read_customers', 'boolean', ['default' => true])
            ->update();
    }
}
