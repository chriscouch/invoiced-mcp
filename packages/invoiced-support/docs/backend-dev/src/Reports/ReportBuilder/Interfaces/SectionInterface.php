<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Companies\Models\Company;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;

interface SectionInterface
{
    public function getTitle(): string;

    public function getDataQuery(): DataQuery;

    public function getCompany(): Company;
}
