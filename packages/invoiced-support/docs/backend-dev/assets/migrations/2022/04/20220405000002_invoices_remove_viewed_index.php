<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesRemoveViewedIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('Invoices');
        $table->removeIndex(['viewed']);
        $table->update();
    }
}
