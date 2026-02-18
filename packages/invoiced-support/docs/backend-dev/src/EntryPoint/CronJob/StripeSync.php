<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\StripeSyncJob;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\StripeGateway;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class StripeSync extends AbstractTaskQueueCronJob
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Queue $queue,
    ) {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return $this->connection->fetchAllAssociative(
            'SELECT DISTINCT merchant_account_id, tenant_id
                FROM PaymentFlows
                WHERE gateway = :gateway
                AND status IN (:status)
                AND merchant_account_id IS NOT NULL
                AND created_at > :created_at',
            [
                'gateway' => StripeGateway::ID,
                'status' => [
                    PaymentFlowStatus::Processing->value,
                    PaymentFlowStatus::ActionRequired->value,
                    PaymentFlowStatus::CollectPaymentDetails->value,
                ],
                'created_at' => CarbonImmutable::now()->subDays(StripeSyncJob::MAX_DAYS),
            ],
            [
                'status' => ArrayParameterType::INTEGER,
            ]
        );
    }

    /**
     * @param array $task
     */
    public function runTask(mixed $task): bool
    {
        $company = Company::find($task['tenant_id']);
        if (null === $company || !$company->billingStatus()->isActive()) {
            return false;
        }

        $this->queue->enqueue(StripeSyncJob::class, [
            'merchantAccountId' => $task['merchant_account_id'],
            'tenant_id' => $task['tenant_id'],
        ], QueueServiceLevel::Batch);

        return true;
    }
}
