<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSyncProfileInvoiceMode extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('write_to_order_entry', 'boolean')
            ->update();
    }
}
