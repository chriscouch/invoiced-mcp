<?php

namespace App\EntryPoint\QueueJob;

use App\Automations\Enums\AutomationTriggerType;
use App\Automations\Models\AutomationWorkflow;
use App\Automations\Models\AutomationWorkflowEnrollment;
use App\Automations\Models\AutomationWorkflowTrigger;
use App\Automations\TriggerAutomation;
use App\Core\Database\TransactionManager;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use Carbon\CarbonImmutable;

class AutomationQueueJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    public function __construct(
        private readonly TriggerAutomation $triggerAutomation,
        private readonly TransactionManager $transactionManager
    ) {
    }

    public function perform(): void
    {
        $triggerId = $this->args['trigger_id'];
        $workflowId = $this->args['workflow_id'];

        $trigger = AutomationWorkflowTrigger::findOrFail($triggerId);
        $workflow = AutomationWorkflow::findOrFail($workflowId);

        $nextRun = $trigger->getNextRun();
        if (!$workflow->enabled || $workflow->deleted || !$nextRun || $nextRun->isAfter(CarbonImmutable::now())) {
            return;
        }

        $models = $workflow->object_type->modelClass()::join(AutomationWorkflowEnrollment::class, 'id', 'AutomationWorkflowEnrollments.object_id')
            ->where('AutomationWorkflowEnrollments.workflow_id', $workflow->id)
            ->all();

        $this->transactionManager->perform(function () use ($models, $workflow, $trigger) {
            foreach ($models as $model) {
                $this->triggerAutomation->initiate($workflow, $model, AutomationTriggerType::Schedule);
            }

            $trigger->advance();
        });
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'automation:'.$args['tenant_id'];
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 3600;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
