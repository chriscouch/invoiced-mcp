<?php

namespace App\Core\Pdf;

use App\Themes\Interfaces\PdfBuilderInterface;

interface PdfDocumentInterface
{
    /**
     * Gets the PDF builder for this object.
     */
    public function getPdfBuilder(): ?PdfBuilderInterface;
}
