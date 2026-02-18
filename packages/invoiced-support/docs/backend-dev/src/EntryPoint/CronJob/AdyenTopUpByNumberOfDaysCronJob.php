<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\AdyenTopUpJob;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use Doctrine\DBAL\Connection;

class AdyenTopUpByNumberOfDaysCronJob extends AbstractTaskQueueCronJob
{
    const int DEFAULT_THRESHOLD_DAYS = 14;

    public function __construct(
        private readonly Connection $connection,
        private readonly Queue $queue,
        private readonly TenantContext $tenant,
    ) {
    }


    public function getTasks(): iterable
    {
        $tasks = [];

        $companies = Company::all();

        foreach ($companies as $company)
        {
            $this->tenant->set($company);

            $merchantAccount = MerchantAccount::withoutDeleted()
                ->where('gateway', AdyenGateway::ID)
                ->where('gateway_id', '0', '<>')
                ->sort('id ASC')
                ->oneOrNull();

            // use merchant account's property if that is not set - use 14 days default
            $numberOfDays = $merchantAccount->top_up_threshold_num_of_days ?? self::DEFAULT_THRESHOLD_DAYS;

            // C - credit, positive | D - debit, negative
            // get all negative for 14 days but avoid ones that have created before 14th day [new companies that are negative of first couple of days]
            $query = "SELECT 
                        SUM(CASE WHEN le.entry_type = 'C' THEN le.amount ELSE 0 END) -
 	                    SUM(CASE WHEN le.entry_type = 'D' THEN le.amount ELSE 0 END) AS net_amount
                    FROM `LedgerEntries` le
                    JOIN `LedgerTransactions` lt ON lt.id = le.transaction_id
                    JOIN `Documents` d ON d.id = lt.document_id
                    JOIN `Ledgers` l ON l.id = d.ledger_id
                    JOIN `Accounts` a ON a.ledger_id = l.id
                    JOIN `DocumentTypes` dt on dt.id = d.document_type_id
                    WHERE a.name = 'Bank Account'
                      AND l.name = :ledgerName
                      AND dt.name IN ('Invoice', 'Payment')
                      AND (
                          SELECT DATEDIFF(CURRENT_DATE(), MIN(lt2.transaction_date))
                          FROM LedgerEntries le2
                          JOIN LedgerTransactions lt2 ON lt2.id = le2.transaction_id
                          JOIN Documents d2 ON d2.id = lt2.document_id
                          WHERE d2.ledger_id = l.id
                      ) >= :numberOfDays;";

            $balanceResult = $this->connection->fetchAllAssociative($query, [
                'numberOfDays' => $numberOfDays,
                'ledgerName' => 'Accounts Payable - ' . $company->id,
            ]);

            // if this company has negative balance for last 14 days [or whatever number admin chooses]
            $netAmount = $balanceResult[0]['net_amount'] ?? 0;
            if($netAmount < 0) { // put all companies with negative balance into tasks
                $tasks[] = [
                    'companyId' => $company->id,
                    'merchantAccountId' => $merchantAccount->id,
                ];
            }
        }

        return $tasks;
    }

    public function runTask(mixed $task): bool
    {
        if (empty($task['merchantAccountId']) || empty($task['companyId'])) {
            return false;
        }

        $this->queue->enqueue(AdyenTopUpJob::class, [
            'merchantAccountId' => $task['merchantAccountId'],
            'companyId' => $task['companyId'],
        ], QueueServiceLevel::Normal);

        return true;
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }
}