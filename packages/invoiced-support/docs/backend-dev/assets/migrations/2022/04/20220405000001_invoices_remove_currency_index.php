<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesRemoveCurrencyIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('Invoices');
        $table->removeIndex(['currency']);
        $table->update();
    }
}
