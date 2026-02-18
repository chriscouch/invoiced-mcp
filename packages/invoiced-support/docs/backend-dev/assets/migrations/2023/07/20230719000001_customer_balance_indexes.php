<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerBalanceIndexes extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();
        $this->table('Invoices')
            // this is coverage index for
            // - customer total outstanding balance calculation
            // - past due balance
            // - due now
            ->addIndex(['customer', 'status', 'paid', 'voided', 'draft', 'closed',  'date', 'currency', 'balance', 'autopay', 'payment_plan_id'])
            ->update();

        $this->table('CreditNotes')
            // - open credit notes
            ->addIndex(['customer', 'closed', 'paid', 'draft', 'voided',  'date', 'currency', 'balance'])
            ->update();
    }
}
