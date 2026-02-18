<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TransactionUpdatedAtIndex extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Transactions')
            ->addIndex(['tenant_id', 'updated_at'])
            ->update();
    }
}
