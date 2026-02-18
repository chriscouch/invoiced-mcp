<?php

namespace App\Core\Csv;

use App\Core\Csv\Interfaces\CsvBuilderInterface;
use Symfony\Component\HttpFoundation\Response;

class CsvStreamer
{
    /**
     * Streams the CSV document.
     */
    public function stream(CsvBuilderInterface $csvBuilder, string $locale): Response
    {
        $csv = $csvBuilder->build($locale);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'public, must-revalidate, max-age=0',
            'Pragma' => 'public',
            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Content-Length' => strlen($csv),
            'Content-Disposition' => 'attachment; filename="'.$csvBuilder->filename($locale).'";',
        ]);
    }
}
