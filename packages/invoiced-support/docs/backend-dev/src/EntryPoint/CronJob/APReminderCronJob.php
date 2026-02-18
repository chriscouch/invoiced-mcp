<?php

namespace App\EntryPoint\CronJob;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\SendAPReminderJob;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class APReminderCronJob extends AbstractTaskQueueCronJob
{
    public function __construct(private readonly Connection $connection, private readonly Queue $queue)
    {
    }

    public static function getName(): string
    {
        return 'ap_reminder';
    }

    public static function getLockTtl(): int
    {
        return 86400;
    }

    public function getTasks(): iterable
    {
        $qb = $this->connection->createQueryBuilder();

        return $qb->select('t.user_id', 't.tenant_id')
            ->from('Tasks', 't')
            ->andWhere($qb->expr()->in('t.action', ':action'))
            ->andWhere('t.complete = 0')
            ->groupBy('t.user_id')
            ->setParameter('action', ['approve_bill', 'approve_vendor_credit'], ArrayParameterType::INTEGER)
            ->fetchAllAssociative();
    }

    /**
     * @param int[] $task
     */
    public function runTask(mixed $task): bool
    {
        $this->queue->enqueue(SendAPReminderJob::class, $task, QueueServiceLevel::Normal);

        return true;
    }
}
