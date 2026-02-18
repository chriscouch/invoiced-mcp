<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesIndexes extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $this->table('Invoices')
            ->addIndex(['tenant_id', 'paid', 'currency', 'date'])
            ->update();

        $this->table('CreditNotes')
            ->addIndex(['tenant_id', 'paid', 'currency', 'date'])
            ->update();
    }
}
