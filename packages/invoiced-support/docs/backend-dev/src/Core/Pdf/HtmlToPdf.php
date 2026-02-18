<?php

namespace App\Core\Pdf;

use App\Core\Pdf\Exception\PdfException;
use App\Core\Pdf\Pdf as CorePdf;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;

class HtmlToPdf implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function makeFromHtml(string $html, array $options): string
    {
        $pdf = CorePdf::make();
        $pdf->addPage($html);
        $pdf->setOptions($options);
        $content = (string) $pdf->toString();

        if (!$content) {
            $this->statsd->increment('pdf.failed');

            throw new PdfException('PDF failed to generate: '.$pdf->getError());
        }

        $this->statsd->increment('pdf.generated');

        return $content;
    }
}
