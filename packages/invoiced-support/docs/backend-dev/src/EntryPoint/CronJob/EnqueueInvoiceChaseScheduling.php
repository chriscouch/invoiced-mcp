<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\ScheduleInvoiceChaseSends;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class EnqueueInvoiceChaseScheduling implements CronJobInterface
{
    public function __construct(private Connection $database, private Queue $queue)
    {
    }

    public static function getName(): string
    {
        return 'enqueue_invoice_chase_scheduling';
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
                $this->queue->enqueue(ScheduleInvoiceChaseSends::class, ['tenant_id' => $company['tenant_id']]);
            }

            ++$page;
            $companies = $this->getCompanies($query, $page);
        }
    }

    /**
     * Returns an array of company ids of companies which have InvoiceDeliveries that need to be scheduled.
     */
    private function getCompanies(QueryBuilder $query, int $page = 1, int $pageSize = 100): array
    {
        $query->setFirstResult($pageSize * ($page - 1))
            ->setMaxResults($pageSize);

        return $query->fetchAllAssociative();
    }

    /**
     * Returns QueryBuilder instance for finding a list of companies which have InvoiceDeliveries that need to be scheduled.
     */
    private function getCompaniesQuery(): QueryBuilder
    {
        return $this->database->createQueryBuilder()
            ->select('tenant_id')
            ->from('InvoiceDeliveries')
            ->where('processed = FALSE')
            ->groupBy('tenant_id');
    }
}
