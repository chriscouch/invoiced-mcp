<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Estimate;

class EstimateImporter extends ReceivableDocumentImporter
{
    protected function getDocumentClass(): string
    {
        return Estimate::class;
    }
}
