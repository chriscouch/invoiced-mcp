<?php

namespace App\Reports\PresetReports;

use App\Companies\Models\Company;
use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\Report;
use App\Reports\ValueObjects\Section;
use App\SubscriptionBilling\Models\MrrVersion;
use Carbon\CarbonImmutable;
use Exception;

abstract class AbstractMrrReport extends AbstractReportBuilderReport
{
    protected function getParameters(array $parameters): array
    {
        if (isset($parameters['$dateRange'])) {
            try {
                $parameters['$startMonth'] = (int) CarbonImmutable::createFromFormat('Y-m-d', $parameters['$dateRange']['start'])->format('Ym'); /* @phpstan-ignore-line */
                $parameters['$endMonth'] = (int) CarbonImmutable::createFromFormat('Y-m-d', $parameters['$dateRange']['end'])->format('Ym'); /* @phpstan-ignore-line */
            } catch (Exception) {
                // Intentionally not throwing an exception here
            }
        }

        return $parameters;
    }

    public function generate(Company $company, array $parameters): Report
    {
        $report = parent::generate($company, $parameters);

        $mrrVersion = $this->company->subscription_billing_settings->mrr_version;
        if ($mrrVersion) {
            $this->addOverviewGroup($report, $mrrVersion);
        }

        return $report;
    }

    protected function addOverviewGroup(Report $report, MrrVersion $mrrVersion): void
    {
        $lastUpdated = $mrrVersion->last_updated;
        if (!$lastUpdated) {
            return;
        }

        $overview = new KeyValueGroup();
        $overview->addLine('Metrics last calculated at', $lastUpdated->format($this->company->date_format.' g:i a'));

        $section = new Section('');
        $section->addGroup($overview);
        $report->addSection($section);
    }
}
