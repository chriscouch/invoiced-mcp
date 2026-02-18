<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesRemoveAutopayIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('Invoices');
        $table->removeIndex(['autopay']);
        $table->update();
    }
}
