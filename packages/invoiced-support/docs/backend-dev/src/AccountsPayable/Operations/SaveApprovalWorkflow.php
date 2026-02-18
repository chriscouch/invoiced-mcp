<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\ApprovalWorkflow;
use App\AccountsPayable\Models\ApprovalWorkflowPath;
use App\AccountsPayable\Models\ApprovalWorkflowStep;
use App\Core\RestApi\Exception\InvalidRequest;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class SaveApprovalWorkflow
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @throws Exception
     */
    public function savePaths(ApprovalWorkflow $workflow, array $paths, bool $isUpdate = false): void
    {
        $ids = [];
        $stepIds = [];
        foreach ($paths as $pathData) {
            $path = (isset($pathData['id']) ? ApprovalWorkflowPath::find($pathData['id']) : null) ?? new ApprovalWorkflowPath();
            $path->tenant_id = $workflow->tenant_id;
            $path->rules = $pathData['rules'];
            if (!$path->persisted()) {
                $path->approval_workflow = $workflow;
            }

            if (!$path->save()) {
                throw new InvalidRequest('Could not save approval workflow paths: '.$path->getErrors());
            }
            $ids[] = $path->id();

            $order = 1;
            foreach ($pathData['steps'] as $stepData) {
                $step = (isset($stepData['id']) ? ApprovalWorkflowStep::find($stepData['id']) : null) ?? new ApprovalWorkflowStep();
                $step->tenant_id = $workflow->tenant_id;
                $step->order = $order;
                $step->minimum_approvers = $stepData['minimum_approvers'];
                $step->members = $stepData['members'];
                $step->roles = $stepData['roles'];
                if (!$step->persisted()) {
                    $step->approval_workflow_path = $path;
                }

                if (!$step->save()) {
                    throw new InvalidRequest('Could not save approval workflow steps: '.$step->getErrors());
                }

                ++$order;
                $stepIds[] = (int) $step->id();
            }
            // remove deleted steps
            if ($isUpdate && count($stepIds) > 0) {
                $this->connection->createQueryBuilder()
                    ->delete('ApprovalWorkflowSteps')
                    ->andWhere('tenant_id = '.$workflow->tenant_id)
                    ->andWhere('approval_workflow_path_id = '.$path->id())
                    ->andWhere('id NOT IN ('.implode(',', $stepIds).')')
                    ->executeStatement();
            }
        }
        // remove deleted steps
        if ($isUpdate && count($ids) > 0) {
            $this->connection->createQueryBuilder()
                ->delete('ApprovalWorkflowPaths')
                ->andWhere('tenant_id = '.$workflow->tenant_id)
                ->andWhere('approval_workflow_id = '.$workflow->id())
                ->andWhere('id NOT IN ('.implode(',', $ids).')')
                ->executeStatement();
        }
    }
}
