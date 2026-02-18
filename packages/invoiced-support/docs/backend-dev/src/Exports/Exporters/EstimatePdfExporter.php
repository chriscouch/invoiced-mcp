<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\Estimate;

/**
 * @extends DocumentPdfExporter<Estimate>
 */
class EstimatePdfExporter extends DocumentPdfExporter
{
    public static function getId(): string
    {
        return 'estimate_pdf';
    }

    public function getClass(): string
    {
        return Estimate::class;
    }
}
