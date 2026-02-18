<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Companies\Models\Company;
use App\Reports\ReportBuilder\Interfaces\SectionInterface;

abstract class AbstractReportSection implements SectionInterface
{
    public function __construct(
        private string $title,
        private DataQuery $dataQuery,
        private Company $company
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDataQuery(): DataQuery
    {
        return $this->dataQuery;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }
}
