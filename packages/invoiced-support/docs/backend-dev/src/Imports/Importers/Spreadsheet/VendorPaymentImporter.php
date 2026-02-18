<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Operations\CreateVendorPayment;
use App\AccountsPayable\Operations\EditVendorPayment;
use App\AccountsPayable\Operations\VoidVendorPayment;
use App\Core\Database\TransactionManager;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\ValueObjects\ImportRecordResult;

class VendorPaymentImporter extends BaseSpreadsheetImporter
{
    private const LINE_ITEM_PROPERTIES = [
        'type' => 'type',
        'bill' => 'bill',
        'vendor_credit' => 'vendor_credit',
        'amount_applied' => 'amount',
    ];

    public function __construct(
        private CreateVendorPayment $create,
        private EditVendorPayment $edit,
        private VoidVendorPayment $void,
        TransactionManager $transactionManager
    ) {
        parent::__construct($transactionManager);
    }

    public function build(array $mapping, array $lines, array $options, Import $import): array
    {
        $options['operation'] ??= self::CREATE;

        $data = [];

        // This is a map that matches vendor payments by number to
        // its index in the import. This allows payments
        // to have multiple line items by sharing a number
        $paymentMap = [];

        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                continue;
            }

            try {
                $record = $this->buildRecord($mapping, $line, $options, $import);

                // determine the payment identifier for the line within this import
                $importIdentifier = $this->importIdentifier($record);

                // build line items
                $lineItem = [];
                foreach (self::LINE_ITEM_PROPERTIES as $k => $property) {
                    if (ImportHelper::cellHasValue($record, $k)) {
                        if ('bill' == $k) {
                            $bill = Bill::where('vendor_id', $record['vendor'] ?? null)
                                ->where('number', $record[$k])
                                ->oneOrNull();
                            if (!$bill) {
                                throw new ValidationException('Could not find bill: '.$record[$k]);
                            }
                            $record[$k] = $bill;
                        } elseif ('vendor_credit' == $k) {
                            $vendorCredit = VendorCredit::where('vendor_id', $record['vendor'] ?? null)
                                ->where('number', $record[$k])
                                ->oneOrNull();
                            if (!$vendorCredit) {
                                throw new ValidationException('Could not find vendor credit: '.$record[$k]);
                            }
                            $record[$k] = $vendorCredit;
                        }

                        $lineItem[$property] = $record[$k];
                    }

                    if (array_key_exists($k, $record)) {
                        unset($record[$k]);
                    }
                }

                if (isset($paymentMap[$importIdentifier])) {
                    $parent = $paymentMap[$importIdentifier];
                    // merge line items with parent
                    if (count($lineItem) > 0) {
                        if (!isset($data[$parent]['applied_to'])) {
                            $data[$parent]['applied_to'] = [];
                        }
                        $data[$parent]['applied_to'][] = $lineItem;
                    }
                } else {
                    if (count($lineItem) > 0) {
                        $record['applied_to'] = [$lineItem];
                    }

                    $data[] = $record;

                    // create new parent to handle multiple lines
                    $paymentMap[$importIdentifier] = count($data) - 1;
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

    protected function findExistingRecord(array $record): ?VendorPayment
    {
        // Payments are identified by number.
        $number = strtolower(trim((string) array_value($record, 'number')));
        if (!$number) {
            return null;
        }

        return VendorPayment::where('number', $number)->oneOrNull();
    }

    //
    // Operations
    //

    protected function createRecord(array $record): ImportRecordResult
    {
        try {
            $appliedTo = $record['applied_to'];
            unset($record['applied_to']);
            $payment = $this->create->create($record, $appliedTo);

            return new ImportRecordResult($payment, ImportRecordResult::CREATE);
        } catch (ModelException $e) {
            throw new RecordException('Could not create record: '.$e->getMessage());
        }
    }

    /**
     * @param VendorPayment $existingRecord
     */
    public function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        try {
            $appliedTo = $record['applied_to'];
            unset($record['applied_to']);
            $this->edit->edit($existingRecord, $record, $appliedTo);

            return new ImportRecordResult($existingRecord, ImportRecordResult::UPDATE);
        } catch (ModelException $e) {
            throw new RecordException('Could not edit payment: '.$e->getMessage());
        }
    }

    /**
     * @param VendorPayment $existingRecord
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

    //
    // Helpers
    //

    /**
     * Get import identifier.
     */
    private function importIdentifier(array $record): string
    {
        // merge multiple line items by a matching vendor and payment number
        $number = trim(strtolower((string) array_value($record, 'number')));
        $vendor = array_value($record, 'vendor');

        if ($vendor instanceof Vendor) {
            $vendor = $vendor->id;
        }

        if (!$vendor && !$number) {
            throw new ValidationException('Missing payment line identifier. The line must include a vendor #, vendor name, or payment number.');
        }

        return $vendor.'-'.$number;
    }
}
