<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Exceptions\AdjustBalanceException;
use App\AccountsReceivable\Libs\ReceivableBalanceAdjuster;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\Traits\DeleteOperationTrait;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\Traits\VoidOperationTrait;
use App\Imports\ValueObjects\ImportRecordResult;

abstract class ReceivableDocumentImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;
    use DeleteOperationTrait;
    use VoidOperationTrait;

    private const LINE_ITEM_PROPERTIES = [
        'item' => 'name',
        'type' => 'type',
        'description' => 'description',
        'quantity' => 'quantity',
        'unit_cost' => 'unit_cost',
        'catalog_item' => 'catalog_item',
    ];

    /**
     * @return class-string<ReceivableDocument>
     */
    abstract protected function getDocumentClass(): string;

    protected function hasShippingParameters(): bool
    {
        return true;
    }

    public function build(array $mapping, array $lines, array $options, Import $import): array
    {
        $options['operation'] ??= self::CREATE;

        $data = [];

        // This is a map that matches documents by number to
        // its index in the import. This allows documents
        // to have multiple line items by sharing a number
        $numbers = [];

        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                continue;
            }

            $itemMetadata = new \stdClass();
            foreach ($mapping as $index => $property) {
                $value = array_value($line, $index);

                // handle line item metadata columns
                if (str_starts_with($property, 'line_item_metadata.')) {
                    if ($value) {
                        $id = str_replace('line_item_metadata.', '', $property);
                        $itemMetadata->$id = $value;
                    }

                    unset($line[$index]);
                }
            }

            try {
                // map values from columns to properties
                $record = $this->buildRecord($mapping, $line, $options, $import);

                // INVD-2593: validate ship_to property
                if (isset($record['ship_to'])) {
                    // check for non-empty ship_to values
                    $shipToEmpty = true;
                    foreach ($record['ship_to'] as $value) {
                        if ($value) {
                            $shipToEmpty = false;
                            break;
                        }
                    }

                    if ($shipToEmpty) {
                        // ShippingDetail object should not be created
                        unset($record['ship_to']);
                    }
                }

                // build line items
                $items = [];
                foreach (self::LINE_ITEM_PROPERTIES as $k => $property) {
                    if (!isset($record[$k])) {
                        continue;
                    }

                    // create the item if needed
                    if (0 == count($items)) {
                        $items[] = [];
                    }

                    // set the item property
                    $items[0][$property] = $record[$k];
                    unset($record[$k]);
                }

                // attach items to document when necessary
                if (count($items) > 0) {
                    // item quantity should default to 1
                    if (!array_key_exists('quantity', $items[0])) {
                        $items[0]['quantity'] = 1;
                    }

                    if (count((array) $itemMetadata) > 0) {
                        $items[0]['metadata'] = $itemMetadata;
                    }

                    $record['items'] = $items;
                }

                $importIdentifier = $this->importIdentifier($record);
                if (ImportHelper::cellHasValue($numbers, $importIdentifier)) {
                    $parent = $numbers[$importIdentifier];
                    // merge line items with parent
                    if (isset($data[$parent]['items']) || isset($record['items'])) {
                        $parentItems = $data[$parent]['items'] ?? [];
                        $recordItems = $record['items'] ?? [];
                        $data[$parent]['items'] = array_merge($parentItems, $recordItems);
                    }
                    // merge rates with parent
                    if (ImportHelper::cellHasValue($record, 'tax')) {
                        $data[$parent]['tax'] = ((float) ($data[$parent]['tax'] ?? 0)) + ((float) $record['tax']);
                    }
                    if (ImportHelper::cellHasValue($record, 'discount')) {
                        $data[$parent]['discount'] = ((float) ($data[$parent]['discount'] ?? 0)) + ((float) $record['discount']);
                    }
                } else {
                    $data[] = $record;

                    // create new parent to handle multiple lines
                    $numbers[$importIdentifier] = count($data) - 1;
                }
            } catch (ValidationException $e) {
                // decorate exception with
                // line number/record and rethrow
                $e->setLineNumber($i + 2)
                    ->setRecord(ImportHelper::mapRecordToColumns($mapping, $line));

                throw $e;
            }
        }

        return $data;
    }

    protected function findExistingRecord(array $record): ?Model
    {
        // Documents are identified by the document number, when provided.
        $number = strtolower(trim(array_value($record, 'number')));
        if (!$number) {
            return null;
        }

        /** @var ReceivableDocument $class */
        $class = $this->getDocumentClass();

        return $class::where('number', $number)->oneOrNull();
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        // remove starting balance from record before import
        $startingBalance = $record['starting_balance'] ?? null;
        unset($record['starting_balance']);

        /** @var ReceivableDocument $class */
        $class = $this->getDocumentClass();
        /** @var ReceivableDocument $document */
        $document = new $class();
        if (isset($record['customer'])) {
            $customer = $this->getCustomerObject($record['customer']);
            if ($customer instanceof Customer) {
                $document->setCustomer($customer);
            }
            unset($record['customer']);
        }
        if (!$document->create($record)) {
            throw new RecordException('Could not create '.$document::modelName().': '.$document->getErrors());
        }

        // set starting balance
        if (is_numeric($startingBalance) && ($document instanceof CreditNote || $document instanceof Invoice)) {
            $this->setStartingBalance($document, (float) $startingBalance);
        }

        return new ImportRecordResult($document, ImportRecordResult::CREATE);
    }

    /**
     * @param ReceivableDocument $existingRecord
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        foreach ($record as $k => $v) {
            // never change the customer on update
            if ('customer' == $k) {
                continue;
            }

            $this->updateExistingRecord($existingRecord, $k, $v);
        }

        if (!$existingRecord->save()) {
            throw new RecordException('Could not save '.$existingRecord::modelName().': '.$existingRecord->getErrors());
        }

        return new ImportRecordResult($existingRecord, ImportRecordResult::UPDATE);
    }

    /**
     * Get import identifier.
     *
     * @throws ValidationException
     */
    private function importIdentifier(array $record): string
    {
        // merge multiple line items by an identifier that
        // is located in this order:
        // 1. document #
        // 2. customer number
        // 3. customer name
        if ($importIdentifier = array_value($record, 'number')) {
            return trim(strtolower($importIdentifier));
        }

        if ($importIdentifier = array_value($record, 'customer.number')) {
            return trim(strtolower($importIdentifier));
        }

        if ($importIdentifier = array_value($record, 'customer.name')) {
            return trim(strtolower($importIdentifier));
        }

        $modelName = $this->getDocumentClass()::modelName();

        throw new ValidationException('Missing '.$modelName.' line identifier. The line must include at least one of document #, customer #, or customer name.');
    }

    /**
     * @throws RecordException
     */
    private function setStartingBalance(Invoice|CreditNote $document, float $startingBalance): void
    {
        try {
            $desiredBalance = Money::fromDecimal($document->currency, $startingBalance);
            ReceivableBalanceAdjuster::sync($document, $desiredBalance);
        } catch (AdjustBalanceException $e) {
            throw new RecordException($e->getMessage());
        }
    }
}
