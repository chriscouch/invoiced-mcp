<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctPaymentSync extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('read_payments', 'boolean')
            ->addColumn('write_payments', 'boolean')
            ->update();
    }
}
