<?php

namespace App\Reports\Interfaces;

use App\Reports\ValueObjects\Report;

interface ReportOutputInterface
{
    /**
     * Generates the raw report output.
     */
    public function generate(Report $report): mixed;
}
