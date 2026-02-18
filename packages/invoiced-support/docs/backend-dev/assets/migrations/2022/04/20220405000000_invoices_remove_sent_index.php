<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesRemoveSentIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('Invoices');
        $table->removeIndex(['sent']);
        $table->update();
    }
}
