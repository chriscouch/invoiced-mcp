<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\ApprovalWorkflow;
use App\AccountsPayable\Models\ApprovalWorkflowStep;
use App\AccountsPayable\Models\PayableDocument;
use App\Chasing\Models\Task;
use App\Companies\Models\Member;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Exception\ModelNotFoundException;

abstract class VendorDocumentOperation
{
    public function __construct(
        protected AccountsPayableLedger $accountsPayableLedger,
        private readonly Connection $connection,
    ) {
    }

    abstract protected function getIdField(): string;

    protected function deleteOutDatedTasks(PayableDocument $model): void
    {
        $field = $this->getIdField();
        $this->connection->createQueryBuilder()
            ->delete('Tasks')
            ->andWhere('tenant_id = :tenant')
            ->andWhere('action = :action')
            ->andWhere('complete = 0')
            ->andWhere($field.'_id = :id')
            ->setParameters([
                'tenant' => $model->tenant_id,
                'action' => $model->getTaskAction(),
                'id' => $model->id,
            ])->executeStatement();
    }

    protected function applyTasks(PayableDocument $model): void
    {
        $field = $this->getIdField();
        $step = $model->approval_workflow_step?->refresh();
        if (!$step) {
            return;
        }
        foreach ($step->members as $member) {
            if (is_int($member)) {
                $member = Member::findOrFail($member);
            }
            $userId = $member->user()->id();
            if (!$userId) {
                continue;
            }
            $task = new Task();
            $task->name = $step->approval_workflow_path->approval_workflow->name.' for '.$model->number;
            $task->action = $model->getTaskAction();
            $task->due_date = time();
            $task->user_id = (int) $userId;
            $task->$field = $model;
            $task->saveOrFail();
        }
    }

    protected function getWorkflow(int|ApprovalWorkflow $approvalWorkflow): ApprovalWorkflow
    {
        return $approvalWorkflow instanceof ApprovalWorkflow ? $approvalWorkflow : ApprovalWorkflow::findOrFail($approvalWorkflow);
    }

    protected function getWorkflowStep(int|ApprovalWorkflowStep $approvalWorkflowStep): ApprovalWorkflowStep
    {
        return $approvalWorkflowStep instanceof ApprovalWorkflowStep ? $approvalWorkflowStep : ApprovalWorkflowStep::findOrFail($approvalWorkflowStep);
    }

    /**
     * @throws ModelNotFoundException
     */
    protected function calculateWorkflow(PayableDocument $model, null|int|ApprovalWorkflow $approvalWorkflow): ?ApprovalWorkflow
    {
        if (null !== $approvalWorkflow) {
            return $this->getWorkflow($approvalWorkflow);
        }

        return $model->vendor->approval_workflow ?? ApprovalWorkflow::where('default', 1)
            ->where('enabled', 1)
            ->oneOrNull();
    }

    protected function calculateWorkflowStep(PayableDocument $model, ApprovalWorkflow|null $workflow, null|int|ApprovalWorkflowStep $approvalWorkflowStep): ?ApprovalWorkflowStep
    {
        if (!$workflow) {
            return null;
        }
        if (null !== $approvalWorkflowStep) {
            return $this->getWorkflowStep($approvalWorkflowStep);
        }
        if (!$path = $workflow->determinePath($model)) {
            return null;
        }
        $step = current($path->getSteps());

        return $step ?: null;
    }
}
