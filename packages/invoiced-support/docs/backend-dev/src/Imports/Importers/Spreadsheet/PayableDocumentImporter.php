<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsPayable\Enums\PayableDocumentSource;
use App\AccountsPayable\Models\PayableDocument;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Operations\VendorDocumentCreateOperation;
use App\AccountsPayable\Operations\VendorDocumentEditOperation;
use App\AccountsPayable\Operations\VendorDocumentVoidOperation;
use App\Core\Database\TransactionManager;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\ValueObjects\ImportRecordResult;

abstract class PayableDocumentImporter extends BaseSpreadsheetImporter
{
    public function __construct(
        protected VendorDocumentCreateOperation $create,
        protected VendorDocumentEditOperation $edit,
        protected VendorDocumentVoidOperation $void,
        TransactionManager $transactionManager,
    ) {
        parent::__construct($transactionManager);
    }

    private const LINE_ITEM_PROPERTIES = [
        'description' => 'description',
        'amount' => 'amount',
    ];

    /**
     * @return class-string<PayableDocument>
     */
    abstract protected function getDocumentClass(): string;

    public function build(array $mapping, array $lines, array $options, Import $import): array
    {
        $options['operation'] ??= self::CREATE;

        $data = [];

        // This is a map that matches documents by vendor + number to
        // its index in the import. This allows documents
        // to have multiple line items.
        $lineMap = [];

        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                continue;
            }

            try {
                // map values from columns to properties
                $record = $this->buildRecord($mapping, $line, $options, $import);

                // build line items
                $lineItem = [];
                foreach (self::LINE_ITEM_PROPERTIES as $source => $destination) {
                    if (!isset($record[$source])) {
                        continue;
                    }

                    // set the item property
                    $lineItem[$destination] = $record[$source];
                    unset($record[$source]);
                }

                $importIdentifier = $this->importIdentifier($record);
                if (isset($lineMap[$importIdentifier])) {
                    $parent = $lineMap[$importIdentifier];
                    // merge line items with parent
                    if (count($lineItem) > 0) {
                        if (!isset($data[$parent]['line_items'])) {
                            $data[$parent]['line_items'] = [];
                        }
                        $data[$parent]['line_items'][] = $lineItem;
                    }
                } else {
                    if (count($lineItem) > 0) {
                        $record['line_items'] = [$lineItem];
                    }

                    $data[] = $record;

                    // create new parent to handle multiple lines
                    $lineMap[$importIdentifier] = count($data) - 1;
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
        // Documents are identified by the vendor and document number, when provided.
        $number = strtolower(trim(array_value($record, 'number')));
        if (!$number) {
            return null;
        }

        $vendor = array_value($record, 'vendor');
        if (!$vendor) {
            return null;
        }

        /** @var PayableDocument $class */
        $class = $this->getDocumentClass();

        return $class::where('vendor_id', $vendor)
            ->where('number', $number)
            ->oneOrNull();
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        try {
            $record['source'] = PayableDocumentSource::Imported;
            $document = $this->create->create($record);

            return new ImportRecordResult($document, ImportRecordResult::CREATE);
        } catch (ModelException $e) {
            throw new RecordException('Could not create record: '.$e->getMessage());
        }
    }

    /**
     * @param PayableDocument $existingRecord
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        try {
            $this->edit->edit($existingRecord, $record);

            return new ImportRecordResult($existingRecord, ImportRecordResult::UPDATE);
        } catch (ModelException $e) {
            throw new RecordException('Could not edit record: '.$e->getMessage());
        }
    }

    /**
     * @param PayableDocument $existingRecord
     */
    protected function voidRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        try {
            $this->void->void($existingRecord);

            return new ImportRecordResult($existingRecord, ImportRecordResult::VOID);
        } catch (ModelException $e) {
            throw new RecordException('Could not void record: '.$e->getMessage());
        }
    }

    /**
     * Get import identifier.
     *
     * @throws ValidationException
     */
    private function importIdentifier(array $record): string
    {
        // merge multiple line items by a matching vendor and document number
        $number = trim(strtolower((string) array_value($record, 'number')));
        $vendor = array_value($record, 'vendor');

        if (!$number || !$vendor instanceof Vendor) {
            $modelName = $this->getDocumentClass()::modelName();

            throw new ValidationException('Missing '.$modelName.' line identifier. The line must include a vendor # or vendor name, and document #.');
        }

        return $vendor->id.'-'.$number;
    }
}
