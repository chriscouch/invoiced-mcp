<?php

namespace App\Reports\Libs;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\BuildReportJob;
use App\Reports\Exceptions\ReportException;
use App\Reports\Models\Report;
use App\Reports\ReportBuilder\DefinitionDeserializer;
use Carbon\CarbonImmutable;

class StartReportJob
{
    public function __construct(private Queue $queue)
    {
    }

    /**
     * @throws ReportException
     */
    public function start(Company $company, ?Member $member, string $type, ?string $definition, array $parameters, bool $send = false): Report
    {
        // check feature flags
        if ('custom' === $type) {
            if (!$company->features->has('report_builder')) {
                throw new ReportException('Your current plan does not support the report builder.');
            }
            if (!$definition) {
                throw new ReportException('A report definition is required for custom reports.');
            }
        }

        // validate report definition
        if ($definition) {
            DefinitionDeserializer::deserialize($definition, $company, $member);
        }

        // create the report model
        $report = new Report();
        $report->type = $type;
        $report->timestamp = CarbonImmutable::now()->getTimestamp();
        $report->title = '';
        $report->filename = '';
        $report->data = [];
        $report->definition = $definition;
        $report->parameters = $parameters;
        $report->saveOrFail();

        // queue it
        $this->queue->enqueue(BuildReportJob::class, [
            'tenant_id' => $company->id(),
            'id' => $report->id(),
            'member' => $member?->id(),
            'send' => $send,
        ]);

        return $report;
    }
}
