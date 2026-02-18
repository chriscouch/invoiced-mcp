<?php

namespace App\EntryPoint\CronJob;

use App\Automations\Enums\AutomationTriggerType;
use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\Queue;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\EntryPoint\QueueJob\AutomationQueueJob;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class AutomationJob implements CronJobInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private readonly Queue $queue, private readonly Connection $connection)
    {
    }

    public static function getLockTtl(): int
    {
        return 3600;
    }

    public function execute(Run $run): void
    {
        // Find any enabled automation triggers for this event type
        $qb = $this->connection->createQueryBuilder();
        $triggers = $qb->select('awt.tenant_id, awt.id as trigger_id, aw.id as workflow_id')
            ->from('AutomationWorkflowTriggers', 'awt')
            ->join('awt', 'AutomationWorkflowVersions', 'awv', 'awt.workflow_version_id=awv.id')
            ->join('awv', 'AutomationWorkflows', 'aw', 'aw.current_version_id=awv.id')
            ->where('trigger_type = :trigger_type')
            ->andWhere('next_run <= :next_run')
            ->andWhere('next_run IS NOT NULL')
            ->andWhere('aw.enabled = true')
            ->andWhere('aw.deleted = false')
            ->setParameter('trigger_type', AutomationTriggerType::Schedule->value)
            ->setParameter('next_run', CarbonImmutable::now())
            ->fetchAllAssociative();

        $n = 0;
        foreach ($triggers as $row) {
            $this->queue->enqueue(AutomationQueueJob::class, $row);
            ++$n;
        }

        $this->statsd->updateStats('cron.processed_task', $n, 1.0, ['cron_job' => static::getName()]);

        $run->writeOutput("Automation initiated for $n triggers");
    }

    public static function getName(): string
    {
        return 'automation';
    }
}
