<?php

namespace App\Integrations\Adyen\Reconciliation;

use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use Generator;

/**
 * This class extracts rows from Adyen CSV reports in storage.
 */
class AdyenReportExtractor
{
    public function __construct(
        private AdyenReportStorage $reportStorage,
    ) {
    }

    /**
     * @throws AdyenReconciliationException
     */
    public function extract(string $filename): Generator
    {
        // Retrieve the report
        $tmpFile = $this->reportStorage->retrieve($filename);
        if (!$tmpFile) {
            throw new AdyenReconciliationException('Report file is missing in storage', $filename);
        }

        // Load the rows one at a time
        $file = fopen($tmpFile->getFileName(), 'r');
        if (!$file) {
            throw new AdyenReconciliationException('Could not open temporary file', $filename);
        }

        $isFirst = true;
        $columnMap = [];
        while (($data = fgetcsv($file)) !== false) {
            // The first row is the column headers
            if ($isFirst) {
                $data = array_filter($data);
                // Normalize column names
                foreach ($data as $i => $name) {
                    $name = str_replace([' ', '(', ')'], ['_', '', ''], strtolower($name));
                    $columnMap[$name] = $i;
                }
                $isFirst = false;
            } else {
                $entry = [];
                foreach ($columnMap as $name => $index) {
                    $entry[$name] = $data[$index] ?? null;
                }

                yield $entry;
            }
        }
        fclose($file);
    }
}
