<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesRemoveTenantIdAndCustomerIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('Invoices');
        if ($table->hasIndex(['tenant_id'])) {
            $table->removeIndex(['tenant_id']);
        }
        if ($table->hasIndex(['customer'])) {
            $table->removeIndex(['customer']);
        }
        $table->update();
    }
}
