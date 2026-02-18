<?php

namespace App\EntryPoint\CronJob;

use App\Core\Multitenant\TenantContext;
use App\Reports\Exceptions\ReportException;
use App\Reports\Libs\ReportScheduler;
use App\Reports\Libs\StartReportJob;
use App\Reports\Models\ScheduledReport;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class ScheduledReportJob extends AbstractTaskQueueCronJob implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private StartReportJob $startReportJob
    ) {
    }

    public static function getName(): string
    {
        return 'scheduled_reports';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return ScheduledReport::queryWithoutMultitenancyUnsafe()
            ->where('next_run', CarbonImmutable::now()->toDateTimeString(), '<=')
            ->all();
    }

    /**
     * @param ScheduledReport $task
     */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();

        // check if the company is in good standing
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        try {
            $savedReport = $task->saved_report;
            $this->startReportJob->start($company, $task->member, 'custom', $savedReport->definition, $task->getParameters(), true);

            // schedule the next report
            $task->last_run = CarbonImmutable::now();
            $task->next_run = ReportScheduler::nextRun($task);
            $task->saveOrFail();

            $saved = true;
        } catch (ReportException $e) {
            $this->logger->warning('Could not start scheduled report', ['exception' => $e, 'report' => $task->id()]);
            $saved = false;
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return $saved;
    }
}
