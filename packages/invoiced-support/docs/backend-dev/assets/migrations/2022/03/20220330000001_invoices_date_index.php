<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesDateIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();
        $this->table('Invoices')
            ->removeIndex(['collection_mode'])
            ->addIndex(['tenant_id', 'date'])
            ->update();
    }
}
