<?php

namespace App\Reports\PresetReports;

use App\Companies\Models\Company;
use App\Reports\Interfaces\PresetReportInterface;
use App\Reports\ReportBuilder\ReportBuilder;
use App\Reports\ValueObjects\Report;

abstract class AbstractReportBuilderReport implements PresetReportInterface
{
    protected Company $company;

    public function __construct(
        protected ReportBuilder $reportBuilder,
    ) {
    }

    /**
     * Gets the report definition in an array format.
     */
    abstract protected function getDefinition(array $parameters): array;

    /**
     * Gets the report parameters to pass into the report builder given
     * the report parameters from the request.
     */
    protected function getParameters(array $parameters): array
    {
        return $parameters;
    }

    public function generate(Company $company, array $parameters): Report
    {
        $this->company = $company;
        $parameters = $this->getParameters($parameters);

        return $this->reportBuilder->build(
            (string) json_encode($this->getDefinition($parameters)),
            $company,
            null,
            $parameters
        );
    }

    protected function getJsonDefinition(string $filename): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/definitions/'.$filename), true);
    }
}
