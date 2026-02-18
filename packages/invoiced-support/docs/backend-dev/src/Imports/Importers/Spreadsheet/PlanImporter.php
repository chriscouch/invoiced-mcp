<?php

namespace App\Imports\Importers\Spreadsheet;

use App\Imports\Models\Import;
use App\SubscriptionBilling\Models\Plan;

/**
 * Spreadsheet importer for plans.
 */
class PlanImporter extends PricingObjectImporter
{
    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        $record = parent::buildRecord($mapping, $line, $options, $import);

        // sanitize type
        if (isset($record['type'])) {
            $record['type'] = strtolower(trim($record['type']));
        }

        return $record;
    }

    protected function getModelClass(): string
    {
        return Plan::class;
    }
}
