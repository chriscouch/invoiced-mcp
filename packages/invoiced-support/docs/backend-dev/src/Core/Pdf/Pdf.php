<?php

namespace App\Core\Pdf;

use App\Core\Pdf\Exception\PdfException;
use mikehaertl\tmp\File;
use mikehaertl\wkhtmlto\Pdf as BasePdf;

class Pdf extends BasePdf implements \Stringable
{
    public function getTempFile(): File
    {
        if (!$this->createPdf()) {
            throw new PdfException('Unable to build PDF: '.$this->getError());
        }

        return $this->_tmpPdfFile;
    }

    public function __toString(): string
    {
        return (string) $this->toString();
    }

    /**
     * Builds a PDF command object with the appropriate options.
     */
    public static function make(): self
    {
        $options = [
            'binary' => '/usr/local/bin/wkhtmltopdf',
            'print-media-type',
            'disable-local-file-access',
            'margin-top' => '0.5cm',
            'margin-left' => '0.5cm',
            'margin-right' => '0.5cm',
            'margin-bottom' => '0.5cm',
            'page-size' => 'letter',
            'encoding' => 'utf8',
            'quiet',
        ];

        return new self($options);
    }
}
