<?php

namespace App\Reports\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\ACLModelRequester;
use App\Reports\Enums\ColumnType;
use App\Reports\Exceptions\ReportException;
use App\Reports\Libs\ReportHelper;
use App\Reports\Libs\ReportStorage;
use App\Reports\Models\Report;
use App\Reports\ReportBuilder\DefinitionDeserializer;
use App\Reports\ValueObjects\AbstractGroup;
use App\Reports\ValueObjects\ChartGroup;
use App\Reports\ValueObjects\FinancialReportColumn;
use App\Reports\ValueObjects\FinancialReportGroup;
use App\Reports\ValueObjects\FinancialReportRow;
use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\MetricGroup;
use App\Reports\ValueObjects\NestedTableGroup;
use App\Reports\ValueObjects\Report as ReportValueObject;
use App\Reports\ValueObjects\Section;
use Carbon\CarbonImmutable;

class DownloadReportRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        protected ReportHelper $helper,
        private ReportStorage $storage,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'format' => new RequestParameter(
                    required: true,
                    allowedValues: ['csv', 'pdf'],
                ),
            ],
            requiredPermissions: ['reports.create'],
            modelClass: Report::class,
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var Report $report */
        $report = parent::buildResponse($context);
        $format = $context->requestParameters['format'];

        $company = $report->tenant();
        $company->useTimezone();
        $this->helper->switchTimezone($company->time_zone);

        try {
            // PDF
            if ('pdf' == $format) {
                if (!$report->pdf_url) {
                    $report->pdf_url = $this->storage->savePdf($this->deserializeValueObject($report));
                    $report->save();
                }

                return [
                    'url' => $report->pdf_url,
                ];
            }

            // CSV
            if (!$report->csv_url) {
                $report->csv_url = $this->storage->saveCsv($this->deserializeValueObject($report));
                $report->save();
            }

            return [
                'url' => $report->csv_url,
            ];
        } catch (ReportException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }

    private function deserializeValueObject(Report $report): ReportValueObject
    {
        if ('Failed' === $report->title && !$report->definition) {
            throw new ReportException('Report failed: '.$report->data['error']);
        }

        $result = new ReportValueObject($report->tenant());
        $result->setTime(CarbonImmutable::createFromTimestamp($report->created_at));
        $result->setTitle($report->title);
        $result->setFilename($report->filename);
        $result->setParameters($report->parameters);

        foreach ($report->data as $sectionData) {
            $result->addSection($this->deserializeSection($sectionData));
        }

        if ($input = $report->definition) {
            $member = ACLModelRequester::get();
            if (!($member instanceof Member)) {
                $member = null;
            }

            $result->setDefinition(
                DefinitionDeserializer::deserialize(
                    $input,
                    $report->tenant(),
                    $member));
        }

        return $result;
    }

    private function deserializeSection(array $sectionData): Section
    {
        $section = new Section($sectionData['title'], $sectionData['class']);

        foreach ($sectionData['groups'] as $groupData) {
            $section->addGroup($this->deserializeGroup($groupData));
        }

        return $section;
    }

    private function deserializeGroup(array $groupData): AbstractGroup
    {
        $type = $groupData['type'];
        if ('chart' == $type) {
            return $this->deserializeChartGroup($groupData);
        }

        if ('keyvalue' == $type) {
            return $this->deserializeKeyValueGroup($groupData);
        }

        if ('metric' == $type) {
            return $this->deserializeMetricGroup($groupData);
        }

        if ('nested_table' == $type) {
            return $this->deserializeNestedTableGroup($groupData);
        }

        if ('financial' == $type) {
            return $this->deserializeFinancialGroup($groupData);
        }

        throw new ReportException('Unrecognized group type: '.$type);
    }

    private function deserializeChartGroup(array $groupData): ChartGroup
    {
        $group = new ChartGroup();
        $group->setChartType($groupData['chart_type']);
        $group->setData($groupData['data']);
        $group->setChartOptions($groupData['options']);

        return $group;
    }

    private function deserializeKeyValueGroup(array $groupData): KeyValueGroup
    {
        $group = new KeyValueGroup();
        $group->addLines($groupData['lines']);

        return $group;
    }

    private function deserializeMetricGroup(array $groupData): MetricGroup
    {
        $group = new MetricGroup();
        $group->addMetrics($groupData['metrics']);

        return $group;
    }

    private function deserializeNestedTableGroup(array $groupData): NestedTableGroup
    {
        $group = new NestedTableGroup($groupData['columns']);

        if (isset($groupData['group'])) {
            $group->setGroupHeader($groupData['group']);
        }

        if (isset($groupData['header'])) {
            $group->setHeader($groupData['header']['columns']);
        }

        if (isset($groupData['footer'])) {
            $group->setFooter($groupData['footer']['columns']);
        }

        foreach ($groupData['rows'] as $rowData) {
            if ('data' == $rowData['type']) {
                $group->addRow($rowData['columns']);
            } else {
                $rowData['columns'] = $group->getColumns();
                $group->addRow($this->deserializeNestedTableGroup($rowData));
            }
        }

        return $group;
    }

    private function deserializeFinancialGroup(array $groupData): FinancialReportGroup
    {
        $columns = [];
        foreach ($groupData['columns'] as $columnData) {
            $columns[] = new FinancialReportColumn($columnData['name'], ColumnType::from($columnData['type']));
        }
        $group = new FinancialReportGroup($columns);

        foreach ($groupData['rows'] as $rowData) {
            $group->addRow($this->deserializeFinancialReportRow($rowData));
        }

        return $group;
    }

    private function deserializeFinancialReportRow(array $rowData): FinancialReportRow
    {
        $row = new FinancialReportRow();
        if (isset($rowData['header'])) {
            $row->setHeader(...$rowData['header']);
        }

        foreach ($rowData['rows'] as $nestedRow) {
            if ('data' == $nestedRow['type']) {
                $row->addValue(...$nestedRow['columns']);
            } else {
                $row->addNestedRow($this->deserializeFinancialReportRow($nestedRow));
            }
        }

        if (isset($rowData['summary'])) {
            $row->setSummary(...$rowData['summary']);
        }

        return $row;
    }
}
