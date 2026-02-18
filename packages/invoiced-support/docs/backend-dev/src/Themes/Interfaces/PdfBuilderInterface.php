<?php

namespace App\Themes\Interfaces;

use App\Core\Pdf\Exception\PdfException;

interface PdfBuilderInterface
{
    /**
     * Generates the filename of generated PDFs.
     */
    public function getFilename(string $locale): string;

    /**
     * Generates a PDF document.
     *
     * @throws PdfException when the PDF cannot be generated
     */
    public function build(string $locale): string;

    /**
     * Generates the HTML used to generate the PDF.
     *
     * @throws PdfException when the HTML cannot be generated
     */
    public function toHtml(string $locale): string;
}
