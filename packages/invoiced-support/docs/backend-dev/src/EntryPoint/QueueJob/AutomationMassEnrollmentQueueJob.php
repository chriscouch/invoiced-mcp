<?php

namespace App\EntryPoint\QueueJob;

use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowEnrollment;
use Throwable;

class AutomationMassEnrollmentQueueJob extends AbstractAutomationEnrollmentQueueJob
{
    public function perform(): void
    {
        $options = $this->args['options'];
        $workflowId = $this->args['workflow_id'];
        $workflow = AutomationWorkflow::find($workflowId);
        if (null === $workflow) {
            return;
        }
        $results = $this->getModels($workflow, $options);

        foreach ($results as $result) {
            $enrollment = new AutomationWorkflowEnrollment();
            $enrollment->workflow = $workflow;
            $enrollment->object_id = (int) $result->id();
            try {
                $enrollment->save();
            } catch (Throwable) {
                // do nothing
            }
        }
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'automation_enrollment:'.$args['tenant_id'];
    }
}
