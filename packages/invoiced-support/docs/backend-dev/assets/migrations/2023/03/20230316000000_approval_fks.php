<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ApprovalFks extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Bills');
        $table->dropForeignKey('approval_workflow_id')
            ->addForeignKey('approval_workflow_id', 'ApprovalWorkflows', 'id')
            ->update();

        $table = $this->table('VendorCredits');
        $table->dropForeignKey('approval_workflow_id')
            ->addForeignKey('approval_workflow_id', 'ApprovalWorkflows', 'id')
            ->update();

        $table = $this->table('ApprovalWorkflowPaths');
        $table->changeColumn('rules', 'text')
            ->dropForeignKey('approval_workflow_id')
            ->addForeignKey('approval_workflow_id', 'ApprovalWorkflows', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();

        $table = $this->table('ApprovalWorkflowSteps');
        $table->dropForeignKey('approval_workflow_path_id')
            ->addColumn('order', 'integer')
            ->addForeignKey('approval_workflow_path_id', 'ApprovalWorkflowPaths', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();

        $table = $this->table('ApprovalWorkflowStepMembers');
        $table->dropForeignKey('approval_workflow_step_id')
            ->dropForeignKey('member_id')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->addForeignKey('member_id', 'Members', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();

        $table = $this->table('ApprovalWorkflowStepRoles');
        $table->dropForeignKey('approval_workflow_step_id')
            ->dropForeignKey('role_id')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->addForeignKey('role_id', 'Roles', 'internal_id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();
    }
}
