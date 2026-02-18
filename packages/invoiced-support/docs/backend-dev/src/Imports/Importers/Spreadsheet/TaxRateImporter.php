<?php

namespace App\Imports\Importers\Spreadsheet;

use App\SalesTax\Models\TaxRate;

/**
 * Spreadsheet importer for tax rates.
 */
class TaxRateImporter extends PricingObjectImporter
{
    protected function getModelClass(): string
    {
        return TaxRate::class;
    }
}
