<?php

namespace App\EntryPoint\QueueJob;

use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\TriggerAutomation;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use Throwable;

class AutomationMassManualTriggerQueueJob extends AbstractAutomationEnrollmentQueueJob
{
    public function __construct(
        private readonly TriggerAutomation $triggerAutomation,
        ListQueryBuilderFactory $factory)
    {
        parent::__construct($factory);
    }

    public function perform(): void
    {
        $options = $this->args['options'];
        $workflowId = $this->args['workflow_id'];
        $workflow = AutomationWorkflow::find($workflowId);
        if (null === $workflow) {
            return;
        }
        $results = $this->getModels($workflow, $options);

        foreach ($results as $object) {
            try {
                $this->triggerAutomation->initiate($workflow, $object, AutomationTriggerType::Manual);
            } catch (Throwable) {
                // do nothing
            }
        }
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'automation_trigger:'.$args['tenant_id'];
    }
}
