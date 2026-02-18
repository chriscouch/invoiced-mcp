<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\AbstractReportSection;
use App\Reports\ReportBuilder\ValueObjects\BarChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\DoughnutChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\LineChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\MetricReportSection;
use App\Reports\ReportBuilder\ValueObjects\PieChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\PolarChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\RadarChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\TableReportSection;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Given the configuration input for a section (i.e. chart or table) generates
 * the section definition.
 */
final class SectionDeserializer
{
    private const ALLOWED_SECTION_TYPES = ['chart', 'metric', 'table'];
    private const ALLOWED_CHART_TYPES = ['bar', 'doughnut', 'line', 'pie', 'polar', 'radar'];

    private static OptionsResolver $resolver;

    /**
     * @throws ReportException
     */
    public static function deserialize(array $input, Company $company, ?Member $member): AbstractReportSection
    {
        try {
            $data = self::getResolver()->resolve($input);
        } catch (ExceptionInterface $e) {
            throw new ReportException('Could not validate section: '.$e->getMessage());
        }

        $query = DataQueryDeserializer::deserialize($data, $company, $member);

        if ('chart' == $data['type']) {
            return self::deserializeChartSection($data, $company, $query);
        } elseif ('metric' == $data['type']) {
            return self::deserializeMetricSection($data, $company, $query);
        } elseif ('table' == $data['type']) {
            return self::deserializeTableSection($data, $company, $query);
        }

        throw new ReportException('Section type not supported: '.$data['type']);
    }

    /**
     * @throws ReportException
     */
    private static function deserializeChartSection(array $data, Company $company, DataQuery $query): AbstractReportSection
    {
        $chartType = $data['chart_type'];
        if ('bar' == $chartType) {
            return new BarChartReportSection($data['title'], $query, $company, $data['chart_options']);
        }

        if ('doughnut' == $chartType) {
            return new DoughnutChartReportSection($data['title'], $query, $company, $data['chart_options']);
        }

        if ('line' == $chartType) {
            return new LineChartReportSection($data['title'], $query, $company, $data['chart_options']);
        }

        if ('pie' == $chartType) {
            return new PieChartReportSection($data['title'], $query, $company, $data['chart_options']);
        }

        if ('polar' == $chartType) {
            return new PolarChartReportSection($data['title'], $query, $company, $data['chart_options']);
        }

        if ('radar' == $chartType) {
            return new RadarChartReportSection($data['title'], $query, $company, $data['chart_options']);
        }

        throw new ReportException('Chart type not supported: '.$chartType);
    }

    private static function deserializeMetricSection(array $data, Company $company, DataQuery $query): MetricReportSection
    {
        return new MetricReportSection($data['title'], $query, $company);
    }

    private static function deserializeTableSection(array $data, Company $company, DataQuery $query): TableReportSection
    {
        return new TableReportSection($data['title'], $query, $company);
    }

    private static function getResolver(): OptionsResolver
    {
        if (isset(self::$resolver)) {
            return self::$resolver;
        }

        $resolver = new OptionsResolver();
        $resolver->setRequired(['fields', 'object', 'type']);
        $resolver->setAllowedTypes('fields', ['array']);
        $resolver->setAllowedTypes('object', ['string']);
        $resolver->setAllowedValues('type', self::ALLOWED_SECTION_TYPES);

        $resolver->setDefined(['chart_type', 'chart_options', 'filter', 'group', 'multi_entity', 'sort', 'title']);
        $resolver->setDefault('chart_type', 'bar');
        $resolver->setAllowedValues('chart_type', self::ALLOWED_CHART_TYPES);
        $resolver->setAllowedTypes('chart_options', ['array']);
        $resolver->setDefault('chart_options', []);
        $resolver->setAllowedTypes('filter', ['array']);
        $resolver->setDefault('filter', []);
        $resolver->setAllowedTypes('group', ['array']);
        $resolver->setDefault('group', []);
        $resolver->setAllowedTypes('multi_entity', ['boolean']);
        $resolver->setDefault('multi_entity', false);
        $resolver->setAllowedTypes('sort', ['array', 'null']);
        $resolver->setDefault('sort', []);
        $resolver->setAllowedTypes('title', ['string']);
        $resolver->setDefault('title', '');
        self::$resolver = $resolver;

        return $resolver;
    }
}
