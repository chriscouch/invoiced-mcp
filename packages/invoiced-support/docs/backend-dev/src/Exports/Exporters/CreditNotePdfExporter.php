<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\CreditNote;

/**
 * @extends DocumentPdfExporter<CreditNote>
 */
class CreditNotePdfExporter extends DocumentPdfExporter
{
    public static function getId(): string
    {
        return 'credit_note_pdf';
    }

    public function getClass(): string
    {
        return CreditNote::class;
    }
}
