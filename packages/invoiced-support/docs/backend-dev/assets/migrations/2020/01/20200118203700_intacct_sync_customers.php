<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSyncCustomers extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('read_customers', 'boolean')
            ->addColumn('write_customers', 'boolean')
            ->update();
    }
}
