<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesUpdatedAtIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();
        $this->table('Invoices')
            ->removeIndex(['updated_at'])
            ->addIndex(['tenant_id', 'updated_at'])
            ->update();
    }
}
