<?php

namespace App\Reports\Interfaces;

use App\Companies\Models\Company;
use App\Reports\Exceptions\ReportException;
use App\Reports\ValueObjects\Report;

interface PresetReportInterface
{
    /**
     * Gets the identifier of this report. The identifier should be
     * unique across all preset reports.
     *
     * Allowed characters: lower case alphanumeric, _
     */
    public static function getId(): string;

    /**
     * Generates the report.
     *
     * @throws ReportException when the report cannot be built
     */
    public function generate(Company $company, array $parameters): Report;
}
