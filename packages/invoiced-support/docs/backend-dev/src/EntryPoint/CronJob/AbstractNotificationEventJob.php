<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\Queue;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\EntryPoint\QueueJob\SendNotificationsJob;
use App\Notifications\Enums\NotificationFrequency;
use Doctrine\DBAL\Connection;

abstract class AbstractNotificationEventJob implements CronJobInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private Queue $queue, private Connection $connection)
    {
    }

    public static function getLockTtl(): int
    {
        return 43200; // 12 hours
    }

    public function execute(Run $run): void
    {
        $tenantIds = $this->connection->createQueryBuilder()
            ->select('tenant_id, member_id')
            ->from('NotificationRecipients')
            ->where('sent = false')
            ->groupBy('tenant_id, member_id')
            ->executeQuery();

        $n = 0;
        while (($row = $tenantIds->fetchAssociative()) !== false) {
            $this->queue->enqueue(SendNotificationsJob::class, [
                'tenant_id' => $row['tenant_id'],
                'member_id' => $row['member_id'],
                'frequency' => $this->getFrequency()->value,
            ]);
            ++$n;
        }

        $this->statsd->updateStats('cron.processed_task', $n, 1.0, ['cron_job' => static::getName()]);

        $run->writeOutput("Queued Notification events assessment for $n members");
    }

    abstract protected function getFrequency(): NotificationFrequency;
}
