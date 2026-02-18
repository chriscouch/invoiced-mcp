<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesPaidCurrencyDateIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('Invoices');

        if ($table->hasIndex(['tenant_id', 'paid', 'date'])) {
            $table->removeIndex(['tenant_id', 'paid', 'date']);
        }
        if ($table->hasIndex(['closed'])) {
            $table->removeIndex(['closed']);
        }
        if ($table->hasIndex(['draft'])) {
            $table->removeIndex(['draft']);
        }

        $table->update();
    }
}
