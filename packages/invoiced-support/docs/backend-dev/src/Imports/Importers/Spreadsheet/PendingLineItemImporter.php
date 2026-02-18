<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Customer;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Models\Import;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\SubscriptionBilling\Models\PendingLineItem;

class PendingLineItemImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;

    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        // map line item custom fields
        $itemMetadata = new \stdClass();
        foreach ($mapping as $index => $property) {
            $value = array_value($line, $index);

            // handle line item metadata columns
            if (str_starts_with($property, 'metadata.')) {
                if ($value) {
                    $id = str_replace('metadata.', '', $property);
                    $itemMetadata->$id = $value;
                }

                unset($line[$index]);
            }
        }

        $record = parent::buildRecord($mapping, $line, $options, $import);

        // item quantity should default to 1
        if (!array_key_exists('quantity', $record)) {
            $record['quantity'] = 1;
        }

        // parse metadata
        if (count((array) $itemMetadata) > 0) {
            $record['metadata'] = $itemMetadata;
        }

        return $record;
    }

    protected function findExistingRecord(array $record): ?Model
    {
        // Finding existing pending line items is currently not supported.
        return null;
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        $lineItem = new PendingLineItem();
        if (isset($record['customer'])) {
            $customer = $this->getCustomerObject($record['customer']);
            if ($customer instanceof Customer) {
                $lineItem->setParent($customer);
            }
            unset($record['customer']);
        }
        if (!$lineItem->create($record)) {
            throw new RecordException('Could not create pending line item: '.$lineItem->getErrors());
        }

        return new ImportRecordResult($lineItem, ImportRecordResult::CREATE);
    }
}
