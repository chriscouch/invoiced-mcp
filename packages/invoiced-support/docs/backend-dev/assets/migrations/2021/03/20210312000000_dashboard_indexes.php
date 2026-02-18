<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DashboardIndexes extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $this->table('Invoices')
            ->addIndex(['tenant_id', 'voided', 'date'])
            ->addIndex(['customer', 'paid'])
            ->removeIndex(['paid'])
            ->removeIndex(['voided'])
            ->update();
    }
}
