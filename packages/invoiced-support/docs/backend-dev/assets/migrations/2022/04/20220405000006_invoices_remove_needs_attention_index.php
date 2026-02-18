<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesRemoveNeedsAttentionIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('Invoices');
        $table->removeIndex(['needs_attention']);
        $table->update();
    }
}
