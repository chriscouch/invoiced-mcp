<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroReadSettings extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroSyncProfiles')
            ->addColumn('read_customers', 'boolean')
            ->addColumn('read_credit_notes', 'boolean')
            ->addColumn('write_customers', 'boolean')
            ->addColumn('write_invoices', 'boolean')
            ->addColumn('write_credit_notes', 'boolean')
            ->addColumn('write_payments', 'boolean')
            ->update();
    }
}
