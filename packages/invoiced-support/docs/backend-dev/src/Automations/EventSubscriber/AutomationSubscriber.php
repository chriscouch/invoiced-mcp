<?php

namespace App\Automations\EventSubscriber;

use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Exception\AutomationException;
use App\Automations\Interfaces\AutomationEventInterface;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Automations\Models\AutomationWorkflowVersion;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\WorkflowRunner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens for a payment created or updated event
 * and initiates a CashMatch job if the payment is
 * unapplied.
 */
class AutomationSubscriber implements EventSubscriberInterface
{
    private static bool $enabled = true;

    public function __construct(private readonly WorkflowRunner $runner)
    {
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public function onEventDispatch(AutomationEventInterface $event): void
    {
        if (!self::$enabled) {
            return;
        }

        // Find any enabled automation triggers for this event type
        /** @var AutomationWorkflowTrigger[] $triggers */
        $triggers = AutomationWorkflowTrigger::join(AutomationWorkflowVersion::class, 'workflow_version_id', 'id')
            ->join(AutomationWorkflow::class, 'AutomationWorkflowVersions.id', 'current_version_id')
            ->where('trigger_type', AutomationTriggerType::Event->value)
            ->where('event_type', $event->eventType())
            ->where('AutomationWorkflows.enabled', true)
            ->where('AutomationWorkflows.deleted', false)
            ->first(100);

        if (0 == count($triggers)) {
            return;
        }

        foreach ($triggers as $trigger) {
            try {
                $context = new AutomationContext(
                    null,
                    $trigger->workflow_version->automation_workflow,
                    $event,
                );
            } catch (AutomationException) {
                // continue to the next trigger
                continue;
            }
            $run = $this->runner->makeRun($trigger, $context);
            $this->runner->queue($run);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'object_event.dispatch' => 'onEventDispatch',
            'automation_event.dispatch' => 'onEventDispatch',
        ];
    }
}
