<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TasksIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Tasks')
            ->addIndex(['user_id', 'complete'])
            ->removeIndex('action')
            ->removeIndex('complete')
            ->removeIndex('user_id')
            ->update();
    }
}
