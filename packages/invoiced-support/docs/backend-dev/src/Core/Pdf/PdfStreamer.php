<?php

namespace App\Core\Pdf;

use App\Core\Pdf\Exception\PdfException;
use App\Themes\Interfaces\PdfBuilderInterface;
use Symfony\Component\HttpFoundation\Response;

class PdfStreamer
{
    /**
     * Streams the PDF document.
     *
     * @throws PdfException when the PDF cannot be generated
     */
    public function stream(PdfBuilderInterface $pdfBuilder, string $locale): Response
    {
        $pdf = $pdfBuilder->build($locale);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'public, must-revalidate, max-age=0',
            'Pragma' => 'public',
            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Content-Length' => strlen($pdf),
            'Content-Disposition' => 'attachment; filename="'.$pdfBuilder->getFilename($locale).'";',
            // allow PDF to be embedded in an iframe
            'X-Frame-Options' => 'ALLOW',
        ]);
    }
}
