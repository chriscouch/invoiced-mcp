<?php

namespace App\Reports\Output;

use App\Reports\Interfaces\ReportOutputInterface;
use App\Reports\ValueObjects\Report;
use Twig\Environment;

class Html implements ReportOutputInterface
{
    public function __construct(private Environment $twig)
    {
    }

    public function generate(Report $report): string
    {
        $viewsDir = dirname(__DIR__, 3).'/templates';
        $cssFile = $viewsDir.'/reports/report.css';
        $timeFormat = $report->getCompany()->date_format.' g:i a';

        $params = [
            'css' => file_get_contents($cssFile),
            'title' => $report->getTitle(),
            'sections' => $report->getSections(),
            'timestamp' => $report->getTime()->format($timeFormat),
            'parameters' => $report->getNamedParameters(),
        ];

        return $this->twig->render('reports/report.twig', $params);
    }
}
