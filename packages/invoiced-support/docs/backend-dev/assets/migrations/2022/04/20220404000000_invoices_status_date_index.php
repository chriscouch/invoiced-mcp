<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesStatusDateIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('Invoices');
        $table->removeIndex(['status']);
        $table->addIndex(['tenant_id', 'status', 'date']);
        $table->update();
    }
}
