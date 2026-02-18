<?php

namespace App\Automations\Traits;

use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Automations\ValueObjects\AutomationEvent;
use App\Core\RestApi\Exception\InvalidRequest;

trait SaveAutomationWorkflowVersionTrait
{
    private function saveTrigger(AutomationWorkflowTrigger $trigger, array $triggerParams): void
    {
        // Look for duplicate triggers
        // TODO

        if (isset($triggerParams['trigger_type'])) {
            foreach (AutomationTriggerType::cases() as $case) {
                if ($case->name == $triggerParams['trigger_type']) {
                    $trigger->trigger_type = $case;

                    break;
                }
            }
        }

        if (AutomationTriggerType::Schedule === $trigger->trigger_type) {
            if (!$triggerParams['r_rule']) {
                throw new InvalidRequest('You have to specify recurrence rule for scheduled triggers '.$trigger->getErrors());
            }
            $trigger->r_rule = $triggerParams['r_rule'];
        }

        if (isset($triggerParams['event_type'])) {
            $trigger->event_type = AutomationEvent::fromEventType($triggerParams['event_type']);
        }

        if (!$trigger->save()) {
            throw new InvalidRequest('Could not save trigger: '.$trigger->getErrors());
        }
    }
}
