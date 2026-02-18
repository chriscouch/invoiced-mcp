<?php

namespace App\Tests\Reports;

use App\Companies\Models\Company;
use App\Reports\ValueObjects\Report;
use App\Reports\ValueObjects\Section;
use App\Tests\AppTestCase;

class ReportTest extends AppTestCase
{
    public function testGetTitle(): void
    {
        $report = new Report(new Company());
        $report->setTitle('Test');
        $this->assertEquals('Test', $report->getTitle());
    }

    public function testGetFilename(): void
    {
        $report = new Report(new Company());
        $report->setFilename('test.pdf');
        $this->assertEquals('test.pdf', $report->getFilename());
    }

    public function testGetSections(): void
    {
        $report = new Report(new Company());
        $section1 = new Section('Test');
        $section2 = new Section('Test');
        $report->addSection($section1)->addSection($section2);

        $this->assertEquals([$section1, $section2], $report->getSections());
    }
}
