<?php

namespace App\Chasing\Pdf;

use App\Chasing\ValueObjects\ChasingEvent;
use App\Core\Pdf\Exception\PdfException;
use App\Core\Pdf\PdfMerger;
use App\Themes\Interfaces\PdfBuilderInterface;
use mikehaertl\tmp\File;

class ChasingPdf implements PdfBuilderInterface
{
    public function __construct(private ChasingEvent $event)
    {
    }

    public function getFilename(string $locale): string
    {
        return 'Invoices.pdf';
    }

    public function build(string $locale): string
    {
        $files = [];
        foreach ($this->event->getInvoices() as $invoice) {
            if ($builder = $invoice->getPdfBuilder()) {
                $files[] = new File($builder->build($locale), 'pdf');
            }
        }

        // merge the documents into a single PDF
        $merger = new PdfMerger();
        $pdf = $merger->merge($files);

        return (string) file_get_contents($pdf);
    }

    public function toHtml(string $locale): string
    {
        throw new PdfException('Not supported');
    }
}
