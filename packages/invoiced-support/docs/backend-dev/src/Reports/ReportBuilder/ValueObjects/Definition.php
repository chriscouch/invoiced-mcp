<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Companies\Models\Company;
use App\Reports\ReportBuilder\Interfaces\SectionInterface;

final class Definition implements \Stringable
{
    /**
     * @param AbstractReportSection[] $sections
     */
    public function __construct(
        private Company $company,
        private string $title,
        private array $sections,
        private string $serialized
    ) {
    }

    public function __toString(): string
    {
        return $this->serialized;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return SectionInterface[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}
