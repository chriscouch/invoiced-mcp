<?php

namespace App\Automations;

use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Exception\AutomationException;
use App\Automations\Models\AutomationRun;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\ValueObjects\AutomationContext;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;

class TriggerAutomation
{
    public function __construct(
        private WorkflowRunner $runner,
    ) {
    }

    /**
     * Initiates a new run of an automation workflow.
     *
     * @throws AutomationException
     */
    public function initiate(AutomationWorkflow $workflow, MultitenantModel $object, AutomationTriggerType $type): AutomationRun
    {
        if (!$workflow->enabled) {
            throw new AutomationException('The workflow is not enabled');
        }

        $objectType = ObjectType::fromModel($object);
        if ($objectType != $workflow->object_type) {
            throw new AutomationException('The workflow is for the '.$workflow->object_type->typeName().' object type. The object given is of type '.$objectType->typeName().'.');
        }

        $version = $workflow->current_version;
        if (!$version) {
            throw new AutomationException('The workflow does not have a published version');
        }

        // find the manual trigger associated with the workflow
        $manualTrigger = null;
        foreach ($version->triggers as $trigger) {
            if ($type == $trigger->trigger_type) {
                $manualTrigger = $trigger;
                break;
            }
        }

        if (!$manualTrigger) {
            throw new AutomationException('The workflow does not allow being triggered '.$type->displayName().'.');
        }

        $context = new AutomationContext(
            $object,
            $workflow,
        );

        $run = $this->runner->makeRun($manualTrigger, $context);
        $this->runner->queue($run);

        return $run;
    }
}
