<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\Invoice;

/**
 * @extends DocumentPdfExporter<Invoice>
 */
class InvoicePdfExporter extends DocumentPdfExporter
{
    public static function getId(): string
    {
        return 'invoice_pdf';
    }

    public function getClass(): string
    {
        return Invoice::class;
    }
}
