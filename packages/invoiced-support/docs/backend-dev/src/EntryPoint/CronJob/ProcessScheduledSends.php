<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\PerformScheduledSendsJob;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class ProcessScheduledSends implements CronJobInterface
{
    public function __construct(private Connection $database, private Queue $queue)
    {
    }

    public static function getName(): string
    {
        return 'process_scheduled_sends';
    }

    public static function getLockTtl(): int
    {
        return 59;
    }

    public function execute(Run $run): void
    {
        $page = 1;
        $query = $this->getCompaniesQuery();
        $companies = $this->getCompanies($query, $page);
        while (count($companies) > 0) {
            foreach ($companies as $company) {
                $this->queue->enqueue(PerformScheduledSendsJob::class, ['tenant_id' => $company['tenant_id']]);
            }

            ++$page;
            $companies = $this->getCompanies($query, $page);
        }
    }

    private function getCompanies(QueryBuilder $query, int $page = 1, int $pageSize = 100): array
    {
        $query->setFirstResult($pageSize * ($page - 1))
            ->setMaxResults($pageSize);

        return $query->fetchAllAssociative();
    }

    private function getCompaniesQuery(): QueryBuilder
    {
        return $this->database->createQueryBuilder()
            ->select('tenant_id')
            ->from('ScheduledSends')
            ->where('sent = FALSE')
            ->andWhere('canceled = FALSE')
            ->andWhere('failed = FALSE')
            ->andWhere('(send_after IS NULL OR CURRENT_TIMESTAMP() >= send_after)')
            ->groupBy('tenant_id');
    }
}
