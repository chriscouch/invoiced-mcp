<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\Estimate;

/**
 * @extends DocumentCsvExporter<Estimate>
 */
class EstimateCsvExporter extends DocumentCsvExporter
{
    protected function getColumnsDocument(): array
    {
        return [
            'number',
            'date',
            'expiration_date',
            'currency',
            'subtotal',
            'total',
        ];
    }

    public static function getId(): string
    {
        return 'estimate_csv';
    }

    public function getClass(): string
    {
        return Estimate::class;
    }
}
