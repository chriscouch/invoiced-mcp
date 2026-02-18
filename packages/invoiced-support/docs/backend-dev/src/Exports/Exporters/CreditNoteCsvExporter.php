<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\CreditNote;

/**
 * @extends DocumentCsvExporter<CreditNote>
 */
class CreditNoteCsvExporter extends DocumentCsvExporter
{
    protected function getColumnsDocument(): array
    {
        return [
            'number',
            'date',
            'currency',
            'subtotal',
            'total',
            'balance',
        ];
    }

    public static function getId(): string
    {
        return 'credit_note_csv';
    }

    public function getClass(): string
    {
        return CreditNote::class;
    }
}
