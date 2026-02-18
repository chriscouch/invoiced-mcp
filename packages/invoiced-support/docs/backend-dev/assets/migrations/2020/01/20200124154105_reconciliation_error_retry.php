<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ReconciliationErrorRetry extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ReconciliationErrors')
            ->addColumn('retry_context', 'text')
            ->addColumn('retried_at', 'integer', ['null' => true, 'default' => null])
            ->addColumn('timestamp', 'integer')
            ->update();
    }
}
