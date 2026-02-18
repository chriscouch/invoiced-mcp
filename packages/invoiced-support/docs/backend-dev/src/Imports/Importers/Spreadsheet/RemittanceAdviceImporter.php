<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\RemittanceAdvice;
use App\CashApplication\Operations\CreateRemittanceAdvice;
use App\Core\Database\TransactionManager;
use App\Core\Orm\Exception\ModelException;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\PaymentProcessing\Models\PaymentMethod;

class RemittanceAdviceImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;

    private const LINE_ITEM_PROPERTIES = [
        'gross_amount_paid' => 'gross_amount_paid',
        'net_amount_paid' => 'net_amount_paid',
        'document_number' => 'document_number',
        'description' => 'description',
        'discount' => 'discount',
    ];

    private const SUPPORTED_PAYMENT_METHODS = [
        PaymentMethod::ACH,
        PaymentMethod::BALANCE,
        PaymentMethod::CASH,
        PaymentMethod::CHECK,
        PaymentMethod::CREDIT_CARD,
        PaymentMethod::DIRECT_DEBIT,
        PaymentMethod::OTHER,
        PaymentMethod::PAYPAL,
        PaymentMethod::WIRE_TRANSFER,
    ];

    public function __construct(
        private CreateRemittanceAdvice $createOperation,
        TransactionManager $transactionManager
    ) {
        parent::__construct($transactionManager);
    }

    public function build(array $mapping, array $lines, array $options, Import $import): array
    {
        $options['operation'] ??= self::CREATE;

        $data = [];

        // This is a map that matches payments by number to
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

                // sanitize payment method
                if (isset($record['payment_method'])) {
                    $record['payment_method'] = $this->parseMethod($record['payment_method']);
                }

                // build line items
                $record['lines'] = [];
                $line = [];
                foreach (self::LINE_ITEM_PROPERTIES as $k => $property) {
                    if (ImportHelper::cellHasValue($record, $k)) {
                        $line[$property] = $record[$k];
                    }

                    if (array_key_exists($k, $record)) {
                        unset($record[$k]);
                    }
                }

                if (isset($line['gross_amount_paid']) || isset($line['net_amount_paid'])) {
                    $record['lines'][] = $line;
                }

                if (isset($paymentMap[$importIdentifier])) {
                    $parentIndex = $paymentMap[$importIdentifier];
                    // merge line items with parent
                    $data[$parentIndex]['lines'] = array_merge($data[$parentIndex]['lines'], $record['lines']);
                } else {
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

    protected function findExistingRecord(array $record): ?RemittanceAdvice
    {
        // Remittance advice is identified by customer AND reference number.
        $reference = array_value($record, 'payment_reference');
        if (!$reference || !isset($record['customer'])) {
            return null;
        }

        $customer = $this->getCustomerObject($record['customer']);
        if (!($customer instanceof Customer)) {
            return null;
        }

        return RemittanceAdvice::where('customer_id', $customer)
            ->where('payment_reference', $reference)
            ->oneOrNull();
    }

    //
    // Operations
    //

    protected function createRecord(array $record): ImportRecordResult
    {
        if (isset($record['customer'])) {
            $record['customer'] = $this->getCustomerObject($record['customer']);
        }

        try {
            $advice = $this->createOperation->create($record);
        } catch (ModelException $e) {
            throw new RecordException('Could not create remittance advice: '.$e->getMessage());
        }

        return new ImportRecordResult($advice, ImportRecordResult::CREATE);
    }

    //
    // Helpers
    //

    private function parseMethod(string $str): string
    {
        $method = strtolower(str_replace(' ', '_', $str));

        // translation for UK users
        if ('cheque' == $method) {
            $method = PaymentMethod::CHECK;
        }

        // translation for AU users
        if ('eft' == $method) {
            $method = PaymentMethod::ACH;
        }

        if (!in_array($method, self::SUPPORTED_PAYMENT_METHODS)) {
            $method = PaymentMethod::OTHER;
        }

        return $method;
    }

    /**
     * Get import identifier.
     */
    private function importIdentifier(array $record): string
    {
        // merge multiple line items by an identifier that
        // is located in this order:
        // 1. customer id + payment method + payment reference
        // 2. customer number + payment method + payment reference
        // 3. customer name + payment method + payment reference
        // 4. payment date + payment method + payment reference

        $importIdentifier = array_value($record, 'customer.id');
        if (!$importIdentifier) {
            $importIdentifier = array_value($record, 'customer.number');
        }
        if (!$importIdentifier) {
            $importIdentifier = array_value($record, 'customer.name');
        }
        if (!$importIdentifier) {
            $importIdentifier = array_value($record, 'payment_date');
        }

        // Always add payment method and reference # to end of identifier.
        // These might be empty.
        return $importIdentifier.'-'.array_value($record, 'payment_method').'-'.array_value($record, 'payment_reference');
    }
}
