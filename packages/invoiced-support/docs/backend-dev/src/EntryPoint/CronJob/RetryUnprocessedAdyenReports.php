<?php

namespace App\EntryPoint\CronJob;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Utils\InfuseUtility as Utility;
use App\EntryPoint\QueueJob\ProcessAdyenReportJob;
use App\Integrations\Adyen\Enums\ReportType;
use App\Integrations\Adyen\Models\AdyenReport;

class RetryUnprocessedAdyenReports extends AbstractTaskQueueCronJob
{
    public function __construct(
        private Queue $queue,
    ) {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        $result = [];
        $accountingReports = AdyenReport::where('processed', false)
            ->where('report_type', ReportType::BalancePlatformAccounting->value)
            ->where('created_at', Utility::unixToDb(time() - 12 * 3600), '>=')
            ->all();

        foreach ($accountingReports as $report) {
            $result[] = $report;
        }

        $payoutReports = AdyenReport::where('processed', false)
            ->where('report_type', ReportType::BalancePlatformPayout->value)
            ->where('created_at', Utility::unixToDb(time() - 12 * 3600), '>=')
            ->all();

        foreach ($payoutReports as $report) {
            $result[] = $report;
        }

        return $result;
    }

    public function runTask(mixed $task): bool
    {
        $this->queue->enqueue(ProcessAdyenReportJob::class, [
            'report' => $task->id,
        ], QueueServiceLevel::Batch);

        return true;
    }
}
