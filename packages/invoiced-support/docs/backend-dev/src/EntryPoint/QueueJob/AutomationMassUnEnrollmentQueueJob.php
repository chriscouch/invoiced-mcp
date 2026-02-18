<?php

namespace App\EntryPoint\QueueJob;

use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowEnrollment;
use Throwable;

class AutomationMassUnEnrollmentQueueJob extends AbstractAutomationEnrollmentQueueJob
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
            try {
                AutomationWorkflowEnrollment::where('workflow_id', $workflowId)
                    ->where('object_id', $result->id())
                    ->delete();
            } catch (Throwable) {
                // do nothing
            }
        }
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'automation_un_enrollment:'.$args['tenant_id'];
    }
}
