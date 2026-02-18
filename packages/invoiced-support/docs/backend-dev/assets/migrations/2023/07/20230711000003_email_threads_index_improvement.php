<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailThreadsIndexImprovement extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();
        $this->table('EmailThreads')
            ->addIndex(['tenant_id', 'status'])
            ->update();
    }
}
