<?php

namespace App\EntryPoint\QueueJob;

use App\Automations\Models\AutomationRun;
use App\Automations\WorkflowRunner;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;

class RunAutomationJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    public function __construct(
        private WorkflowRunner $runner,
    ) {
    }

    public function perform(): void
    {
        $run = AutomationRun::find($this->args['run_id']);
        if (!$run) {
            return;
        }

        $this->runner->start($run);
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 1 automation per account can be processed at a time.
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'automation:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 60; // 1 minute
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
