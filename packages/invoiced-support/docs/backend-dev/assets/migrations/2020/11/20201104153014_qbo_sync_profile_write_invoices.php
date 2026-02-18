<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QboSyncProfileWriteInvoices extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('write_invoices', 'boolean', ['default' => true])
            ->update();
    }
}
