<?php

namespace App\EntryPoint\QueueJob;

use App\Companies\Models\Member;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Reports\Exceptions\ReportException;
use App\Reports\Libs\PresetReportFactory;
use App\Reports\Models\Report;
use App\Reports\Output\Json;
use App\Reports\ReportBuilder\ReportBuilder;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class BuildReportJob extends AbstractResqueJob implements LoggerAwareInterface, TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private PresetReportFactory $factory,
        private ReportBuilder $builder,
        private Json $json,
        private Mailer $mailer,
        private string $dashboardUrl
    ) {
    }

    public function perform(): void
    {
        $this->build(
            $this->args['member'] ? Member::findOrFail($this->args['member']) : null,
            Report::findOrFail($this->args['id']),
            $this->args['send'] ?? false,
        );
    }

    public function build(?Member $member, Report $report, bool $send): void
    {
        try {
            $parameters = $report->parameters ?? [];
            $company = $this->tenant->get();

            // build the report
            if ('custom' == $report->type) {
                $builtReport = $this->builder->build((string) $report->definition, $company, $member, $parameters);
            } else {
                $presetReport = $this->factory->get($report->type);
                $builtReport = $presetReport->generate($company, $parameters);
            }

            // update the reports with the result
            $report->title = $builtReport->getTitle();
            $report->filename = $builtReport->getFilename();
            $report->timestamp = CarbonImmutable::now()->getTimestamp();
            $report->definition = $builtReport->getDefinition();
            $report->parameters = $builtReport->getParameters();
            $report->data = $this->json->generate($builtReport);
            $report->saveOrFail();

            if ($send && $member instanceof Member) {
                $this->send($report, $member);
            }
        } catch (Throwable $e) {
            // Only log ReportException if caused by another exception.
            if (!$e instanceof ReportException) {
                $this->logger->error('Could not build report', ['exception' => $e]);
            }

            $msg = ($e instanceof ReportException) ? $e->getMessage() : 'Report failed to build';
            $report->title = 'Failed';
            $report->timestamp = CarbonImmutable::now()->getTimestamp();
            $report->data = ['error' => $msg];
            $report->save();
        }
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 3 reports can be built at a time.
        return 3;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'build_report:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 300; // 5 minutes
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }

    private function send(Report $report, Member $member): void
    {
        $user = $member->user();
        $message = [
            'subject' => 'Scheduled Report: '.$report->title,
        ];
        $variables = [
            'name' => $user->name(),
            'reportName' => $report->title,
            'url' => $this->dashboardUrl.'/reports/'.$report->id().'?account='.$report->tenant_id,
        ];

        $this->mailer->sendToUser($user, $message, 'scheduled-report', $variables);
    }
}
