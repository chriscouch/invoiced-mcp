<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Automations extends MultitenantModelMigration
{
    public function change(): void
    {
        $workflowsTable = $this->table('AutomationWorkflows');
        $this->addTenant($workflowsTable);
        $workflowsTable->addColumn('name', 'string')
            ->addColumn('description', 'string', ['length' => 1000, 'null' => true, 'default' => null])
            ->addColumn('object_type', 'smallinteger')
            ->addColumn('current_version_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('draft_version_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('enabled', 'boolean')
            ->addColumn('deleted', 'boolean')
            ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex(['tenant_id', 'name'], ['unique' => true])
            ->create();

        $versionsTable = $this->table('AutomationWorkflowVersions');
        $this->addTenant($versionsTable);
        $versionsTable->addColumn('automation_workflow_id', 'integer')
            ->addColumn('version', 'integer')
            ->addTimestamps()
            ->addIndex(['automation_workflow_id', 'version'], ['unique' => true])
            ->create();

        $triggersTable = $this->table('AutomationWorkflowTriggers');
        $this->addTenant($triggersTable);
        $triggersTable->addColumn('workflow_version_id', 'integer')
            ->addColumn('trigger_type', 'smallinteger')
            ->addColumn('event_type', 'smallinteger')
            ->addTimestamps()
            ->create();

        $stepsTable = $this->table('AutomationWorkflowSteps');
        $this->addTenant($stepsTable);
        $stepsTable->addColumn('workflow_version_id', 'integer')
            ->addColumn('action_type', 'smallinteger')
            ->addColumn('settings', 'text')
            ->addColumn('order', 'smallinteger')
            ->addTimestamps()
            ->create();

        $runsTable = $this->table('AutomationRuns', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true]);
        $this->addTenant($runsTable);
        $runsTable->addColumn('workflow_version_id', 'integer')
            ->addColumn('trigger_id', 'integer')
            ->addColumn('context', 'text')
            ->addColumn('result', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('finished_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();

        $stepRunsTable = $this->table('AutomationStepRuns', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['signed' => false, 'identity' => true]);
        $this->addTenant($stepRunsTable);
        $stepRunsTable->addColumn('workflow_run_id', 'biginteger', ['signed' => false])
            ->addColumn('workflow_step_id', 'integer')
            ->addColumn('result', 'smallinteger')
            ->addColumn('error_message', 'string', ['null' => true, 'default' => null])
            ->addColumn('finished_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();

        // Foreign Keys
        $workflowsTable->addForeignKey('current_version_id', 'AutomationWorkflowVersions', 'id', ['update' => 'CASCADE', 'delete' => 'SET NULL'])
            ->addForeignKey('draft_version_id', 'AutomationWorkflowVersions', 'id', ['update' => 'CASCADE', 'delete' => 'SET NULL'])
            ->update();

        $versionsTable->addForeignKey('automation_workflow_id', 'AutomationWorkflows', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();

        $triggersTable->addForeignKey('workflow_version_id', 'AutomationWorkflowVersions', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();

        $stepsTable->addForeignKey('workflow_version_id', 'AutomationWorkflowVersions', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();

        $runsTable->addForeignKey('workflow_version_id', 'AutomationWorkflowVersions', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->addForeignKey('trigger_id', 'AutomationWorkflowTriggers', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();

        $stepRunsTable->addForeignKey('workflow_run_id', 'AutomationRuns', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->addForeignKey('workflow_step_id', 'AutomationWorkflowSteps', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();
    }
}
