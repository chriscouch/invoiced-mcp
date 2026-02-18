<?php

namespace App\Chasing\Libs;

use App\Chasing\Models\ChasingStatistic;
use App\Companies\Models\Company;
use App\Core\Database\TransactionManager;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class ChasingStatisticsRepository
{
    public function __construct(private readonly Connection $connection, private readonly TransactionManager $transactionManager)
    {
    }

    /**
     * @param ChasingStatistic[] $statistics
     *
     * @throws Exception
     */
    public function massUpdate(Company $company, array $statistics): void
    {
        $invoiceIds = array_unique(array_map(fn ($statistic) => $statistic->invoice_id, $statistics));
        $qb = $this->connection->createQueryBuilder();
        $qb->update('ChasingStatistics')
            ->set('paid', ':paid')
            ->set('payment_responsible', ':payment_responsible')
            ->where('tenant_id = :company')
            ->andWhere($qb->expr()->in('invoice_id', ':invoice_id'))
            ->andWhere('paid IS NULL')
            ->setParameter('paid', CarbonImmutable::now()->toIso8601String())
            ->setParameter('payment_responsible', false)
            ->setParameter('company', $company->id)
            ->setParameter('invoice_id', $invoiceIds, ArrayParameterType::INTEGER)
            ->executeQuery();

        $this->transactionManager->perform(function () use ($statistics) {
            foreach ($statistics as $statistic) {
                $statistic->save();
            }
        });
    }
}
