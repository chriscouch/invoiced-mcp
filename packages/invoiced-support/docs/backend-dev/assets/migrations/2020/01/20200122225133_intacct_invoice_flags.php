<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctInvoiceFlags extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('read_invoices', 'boolean')
            ->addColumn('write_invoices', 'boolean')
            ->update();
    }
}
