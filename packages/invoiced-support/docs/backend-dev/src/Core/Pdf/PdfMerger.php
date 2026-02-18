<?php

namespace App\Core\Pdf;

use App\Core\Pdf\Exception\PdfException;
use mikehaertl\tmp\File;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Merges one or more PDF documents into a single
 * PDF file.
 */
class PdfMerger
{
    /**
     * @param File[] $files
     *
     * @throws PdfException
     */
    public function merge(array $files): File
    {
        if (1 === count($files)) {
            return $files[0];
        }

        try {
            // Merge the PDFs using Ghostscript
            $mergedPdf = new File('', 'pdf');
            $params = ['gs', '-q', '-dNOPAUSE', '-dBATCH', '-sDEVICE=pdfwrite', '-dPrinted=false', '-sOutputFile='.$mergedPdf];

            foreach ($files as $file) {
                $params[] = (string) $file;
            }

            $process = new Process($params);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new PdfException($process->getErrorOutput());
            }

            return $mergedPdf;
        } catch (ExceptionInterface $e) {
            throw new PdfException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
