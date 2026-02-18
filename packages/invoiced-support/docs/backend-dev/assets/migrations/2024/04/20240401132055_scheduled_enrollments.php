<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ScheduledEnrollments extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AutomationWorkflowEnrollments');
        $this->addTenant($table);
        $table->addColumn('workflow_id', 'integer')
            ->addColumn('object_id', 'integer')
            ->addIndex(['workflow_id', 'object_id'], ['unique' => true])
            ->create();
    }
}
