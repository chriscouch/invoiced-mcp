<?php

namespace App\Reports\Output;

use App\Core\Pdf\HtmlToPdf;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Reports\Interfaces\ReportOutputInterface;
use App\Reports\ValueObjects\Report;

class Pdf extends HtmlToPdf implements ReportOutputInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(private Html $htmlGenerator)
    {
    }

    public function generate(Report $report): string
    {
        $html = $this->htmlGenerator->generate($report);

        return $this->makeFromHtml($html, ['orientation' => 'Portrait']);
    }
}
