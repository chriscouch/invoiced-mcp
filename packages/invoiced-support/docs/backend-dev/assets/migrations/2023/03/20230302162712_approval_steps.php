<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ApprovalSteps extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Vendors');
        $table->addColumn('approval_workflow_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('approval_workflow_id', 'ApprovalWorkflows', 'id')
            ->update();

        $table = $this->table('Tasks');
        $table->addColumn('bill_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('vendor_credit_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('bill_id', 'Bills', 'id')
            ->addForeignKey('vendor_credit_id', 'VendorCredits', 'id')
            ->update();

        $table = $this->table('Bills');
        $table->addColumn('approval_workflow_step_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('approval_workflow_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('approval_status', 'tinyinteger')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id')
            ->addForeignKey('approval_workflow_id', 'ApprovalWorkflowSteps', 'id')
            ->update();

        $table = $this->table('VendorCredits');
        $table->addColumn('approval_workflow_step_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('approval_workflow_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('approval_status', 'tinyinteger')
            ->addForeignKey('approval_workflow_step_id', 'ApprovalWorkflowSteps', 'id')
            ->addForeignKey('approval_workflow_id', 'ApprovalWorkflowSteps', 'id')
            ->update();
    }
}
