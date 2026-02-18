<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Reports\ValueObjects\Section;

interface FormatterInterface
{
    public function format(SectionInterface $section, array $data, array $parameters): Section;
}
