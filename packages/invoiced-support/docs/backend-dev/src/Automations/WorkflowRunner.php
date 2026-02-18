<?php

namespace App\Automations;

use App\Automations\Actions\AutomationActionFactory;
use App\Automations\Enums\AutomationResult;
use App\Automations\EventSubscriber\AutomationSubscriber;
use App\Automations\Exception\AutomationException;
use App\Automations\Models\AutomationRun;
use App\Automations\Models\AutomationStepRun;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Automations\ValueObjects\AutomationContext;
use App\Automations\ValueObjects\AutomationOutcome;
use App\Core\Database\TransactionManager;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\EntryPoint\QueueJob\RunAutomationJob;
use App\ActivityLog\Interfaces\EventStorageInterface;
use Carbon\CarbonImmutable;

class WorkflowRunner implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private readonly Queue $queue,
        private readonly AutomationActionFactory $actionFactory,
        private readonly TransactionManager $transactionManager,
        private readonly EventStorageInterface $storage,
    ) {
    }

    /**
     * @throws AutomationException
     */
    public function makeRun(AutomationWorkflowTrigger $trigger, AutomationContext $context): AutomationRun
    {
        $run = new AutomationRun();
        $run->workflow_version = $trigger->workflow_version;
        $run->trigger = $trigger;
        $run->object_type = $context->objectType;
        $run->object_id = $context->getObjectId();
        $run->event = $context->event?->self();
        $run->event_type_id = $context->event?->eventType();

        if (!$run->save()) {
            throw new AutomationException((string) $run->getErrors());
        }

        $this->statsd->increment('automations.queued', 1.0, ['trigger_type' => $trigger->trigger_type->name]);

        return $run;
    }

    public function queue(AutomationRun $run): void
    {
        $this->queue->enqueue(RunAutomationJob::class, [
            'tenant_id' => $run->tenant_id,
            'run_id' => $run->id,
        ], QueueServiceLevel::Normal);
    }

    /**
     * @return AutomationStepRun[]
     */
    public function start(AutomationRun $run): array
    {
        $run->tenant()->useTimezone();
        // Ensure that events caused by an automation do not result
        // in more automations being initiated. This could result in
        // an infinite loop.
        AutomationSubscriber::disable();

        $this->statsd->increment('automations.started_run');

        try {
            $context = AutomationContext::fromRun($run, $this->storage);
        } catch (AutomationException) {
            $run->finished_at = CarbonImmutable::now();
            $run->result = AutomationResult::Failed;
            $run->saveOrFail();

            return [];
        }

        $run->result = AutomationResult::Pending;
        $run->saveOrFail();

        // TODO: ensure these are sorted by the order
        $steps = $run->workflow_version->steps;
        $index = 0;
        $nextStep = $steps[$index] ?? null;
        $results = [];
        $lastRunResult = AutomationResult::Succeeded;
        while ($nextStep) {
            $action = $this->actionFactory->make($nextStep->action_type);

            $stepRun = new AutomationStepRun();
            $stepRun->workflow_run = $run;
            $stepRun->workflow_step = $nextStep;
            $stepRun->result = AutomationResult::Pending;
            $stepRun->saveOrFail();

            // Perform each action in a separate transaction
            /* @var AutomationOutcome $outcome */
            try {
                $outcome = $this->transactionManager->perform(function () use ($action, $nextStep, $context) {
                    return $action->perform($nextStep->settings, $context);
                });
            } catch (AutomationException $e) {
                $outcome = new AutomationOutcome(AutomationResult::Failed, $e->getMessage());
            }

            $results[] = $stepRun;
            $stepRun->result = $outcome->result;
            $stepRun->error_message = $outcome->errorMessage;
            $stepRun->finished_at = CarbonImmutable::now();
            $stepRun->saveOrFail();

            $this->statsd->increment('automations.perform_action', 1.0, [
                'action_type' => $nextStep->action_type->name,
                'result' => $outcome->result->name,
            ]);

            $lastRunResult = $outcome->result;

            if (AutomationResult::Succeeded !== $outcome->result || $outcome->terminate) {
                // stop if the result was not successful or condition not met
                break;
            }
            // go to the next step if the result was successful
            ++$index;
            $nextStep = $steps[$index] ?? null;
        }

        $run->result = $lastRunResult;
        if (in_array($lastRunResult, [AutomationResult::Succeeded, AutomationResult::Stop, AutomationResult::Failed])) {
            $run->finished_at = CarbonImmutable::now();
            $time = (float) (time() - $run->created_at);
            $this->statsd->increment('automations.finished_run', 1.0, ['result' => $lastRunResult->name]);
            $this->statsd->timing('automations.run_time', $time);
        }

        $run->saveOrFail();

        return $results;
    }
}
