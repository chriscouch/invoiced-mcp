<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ApprovalWorkflows extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('ApprovalWorkflows');
        $this->addTenant($table);
        $table->addTimestamps()
            ->addColumn('name', 'string')
            ->addColumn('default', 'boolean')
            ->addColumn('enabled', 'boolean')
            ->create();

        $table = $this->table('ApprovalWorkflowPaths');
        $this->addTenant($table);
        $table->addColumn('rules', 'json')
            ->addColumn('approval_workflow_id', 'integer')
            ->addForeignKey('approval_workflow_id', 'ApprovalWorkflows', 'id')
            ->create();

        $table = $this->table('ApprovalWorkflowSteps');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('approval_workflow_path_id', 'integer')
            ->addColumn('type', 'tinyinteger')
            ->addColumn('minimum_approvers', 'tinyinteger')
            ->addForeignKey('approval_workflow_path_id', 'ApprovalWorkflowPaths', 'id')
            ->create();
    }
}
