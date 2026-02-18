<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Approvals extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('ApprovalWorkflowStepRoles', ['id' => false, 'primary_key' => ['approval_workflow_step_id', 'role_id']]);
        $table->addColumn('approval_workflow_step_id', 'integer')
            ->addColumn('role_id', 'integer')
            ->addForeignKey('role_id', 'Roles', 'internal_id')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id')
            ->create();

        $table = $this->table('ApprovalWorkflowStepMembers', ['id' => false, 'primary_key' => ['approval_workflow_step_id', 'member_id']]);
        $table->addColumn('approval_workflow_step_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addForeignKey('member_id', 'Members', 'id')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id')
            ->create();

        $table = $this->table('BillApprovals');
        $this->addTenant($table);
        $table->addColumn('bill_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addColumn('approval_workflow_step_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('note', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('bill_id', 'Bills', 'id')
            ->addForeignKey('member_id', 'Members', 'id')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id')
            ->create();

        $table = $this->table('BillRejections');
        $this->addTenant($table);
        $table->addColumn('bill_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addColumn('approval_workflow_step_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('note', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('bill_id', 'Bills', 'id')
            ->addForeignKey('member_id', 'Members', 'id')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id')
            ->create();

        $table = $this->table('VendorCreditApprovals');
        $this->addTenant($table);
        $table->addColumn('vendor_credit_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addColumn('approval_workflow_step_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('note', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('vendor_credit_id', 'VendorCredits', 'id')
            ->addForeignKey('member_id', 'Members', 'id')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id')
            ->create();

        $table = $this->table('VendorCreditRejections');
        $this->addTenant($table);
        $table->addColumn('vendor_credit_id', 'integer')
            ->addColumn('member_id', 'integer')
            ->addColumn('approval_workflow_step_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('note', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('vendor_credit_id', 'VendorCredits', 'id')
            ->addForeignKey('member_id', 'Members', 'id')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id')
            ->create();
    }
}
