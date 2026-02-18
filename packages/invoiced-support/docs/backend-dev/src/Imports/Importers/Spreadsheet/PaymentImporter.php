<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Libs\CashApplicationMatchmaker;
use App\CashApplication\Libs\DuplicatePaymentsReconciler;
use App\CashApplication\Models\Payment;
use App\Core\Database\TransactionManager;
use App\Core\Orm\Model;
use App\Core\Utils\RandomString;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\Traits\ImportAccountingParametersTrait;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\Traits\VoidOperationTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\PaymentProcessing\Models\PaymentMethod;

class PaymentImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;
    use VoidOperationTrait;
    use ImportAccountingParametersTrait;

    private const LINE_ITEM_PROPERTIES = [
        'type' => 'type',
        'document_type' => 'document_type',
        'invoice' => 'invoice',
        'estimate' => 'estimate',
        'credit_note' => 'credit_note',
        'amount_applied' => 'amount',
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
    private array $invoices = [];
    private array $creditNotes = [];
    private array $estimates = [];

    public function __construct(
        private DuplicatePaymentsReconciler $reconciler,
        private CashApplicationMatchmaker $matchmaker,
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
                if (isset($record['method'])) {
                    $record['method'] = $this->parseMethod($record['method']);
                }

                // set the source
                $record['source'] = 'imported';

                // build line items
                $record['applied_to'] = [];
                $appliedTo = [];
                foreach (self::LINE_ITEM_PROPERTIES as $k => $property) {
                    if (ImportHelper::cellHasValue($record, $k)) {
                        if ('invoice' == $k) {
                            $record[$k] = $this->lookupInvoice($record[$k]);
                        } elseif ('estimate' == $k) {
                            $record[$k] = $this->lookupEstimate($record[$k]);
                        } elseif ('credit_note' == $k) {
                            $record[$k] = $this->lookupCreditNote($record[$k]);
                        }

                        if ('type' == $k) {
                            $record[$k] = strtolower($record[$k]);
                        }

                        $appliedTo[$property] = $record[$k];
                    }

                    if (array_key_exists($k, $record)) {
                        unset($record[$k]);
                    }
                }

                if (isset($appliedTo['type']) && isset($appliedTo['amount'])) {
                    $record['applied_to'][] = $appliedTo;
                }

                $record = $this->buildRecordAccounting($record);

                if (isset($paymentMap[$importIdentifier])) {
                    $parentIndex = $paymentMap[$importIdentifier];
                    // merge line items with parent
                    $data[$parentIndex]['applied_to'] = array_merge($data[$parentIndex]['applied_to'], $record['applied_to']);
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

    protected function findExistingRecord(array $record): ?Payment
    {
        // Payments are identified by customer AND reference number.
        $reference = array_value($record, 'reference');
        if (!$reference || !isset($record['customer'])) {
            return null;
        }

        $customer = $this->getCustomerObject($record['customer']);
        if (!($customer instanceof Customer)) {
            return null;
        }

        return Payment::where('customer', $customer)
            ->where('reference', $reference)
            ->oneOrNull();
    }

    //
    // Operations
    //

    protected function createRecord(array $record): ImportRecordResult
    {
        /* Add the applied to models back */

        foreach ($record['applied_to'] as &$lineItem) {
            if (isset($lineItem['credit_note'])) {
                $lineItem['credit_note'] = $this->creditNotes[$lineItem['credit_note']];
            }
            if (isset($lineItem['estimate'])) {
                $lineItem['estimate'] = $this->estimates[$lineItem['estimate']];
            }
            if (isset($lineItem['invoice'])) {
                $lineItem['invoice'] = $this->invoices[$lineItem['invoice']];
            }
        }

        /* Create the payment */

        $payment = new Payment();
        if (isset($record['customer'])) {
            $customer = $this->getCustomerObject($record['customer']);
            if ($customer instanceof Customer) {
                $payment->setCustomer($customer);
            }
            unset($record['customer']);
        }

        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        foreach ($record as $k => $v) {
            $payment->$k = $v;
        }

        if ($existingPayment = $this->reconciler->detectDuplicatePayment($payment)) {
            $payment = $this->reconciler->mergeDuplicatePayments($existingPayment, $record);

            return new ImportRecordResult($payment, ImportRecordResult::UPDATE);
        }

        if (!$payment->create()) {
            throw new RecordException('Could not create payment: '.$payment->getErrors());
        }

        // save accounting mapping
        if ($accountingSystem && $accountingId) {
            $this->saveAccountingMapping($payment, $accountingSystem, $accountingId);
        }

        // If the payment does not have a customer then we need to
        // start a cash match job. This has to be done here because
        // the import tool does not create payment.created events
        // that would start the cash match job.
        if ($this->matchmaker->shouldLookForMatches($payment)) {
            $this->matchmaker->enqueue($payment, false);
        }

        return new ImportRecordResult($payment, ImportRecordResult::CREATE);
    }

    /**
     * @param Payment $existingRecord
     */
    public function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        /* Add the applied to models back */

        foreach ($record['applied_to'] as &$lineItem) {
            if (isset($lineItem['credit_note'])) {
                $lineItem['credit_note'] = $this->creditNotes[$lineItem['credit_note']];
            }
            if (isset($lineItem['estimate'])) {
                $lineItem['estimate'] = $this->estimates[$lineItem['estimate']];
            }
            if (isset($lineItem['invoice'])) {
                $lineItem['invoice'] = $this->invoices[$lineItem['invoice']];
            }
        }

        /* Update the payment */

        // the customer on a payment can change
        if (isset($record['customer'])) {
            $customer = $this->getCustomerObject($record['customer']);
            if ($customer instanceof Customer) {
                $existingRecord->setCustomer($customer);
            }
            unset($record['customer']);
        }

        // pull out mapping fields
        $accountingSystem = $record['accounting_system'] ?? null;
        $accountingId = $record['accounting_id'] ?? null;
        unset($record['accounting_system']);
        unset($record['accounting_id']);

        foreach ($record as $k => $v) {
            $existingRecord->$k = $v;
        }

        if (!$existingRecord->save()) {
            throw new RecordException('Could not update payment: '.$existingRecord->getErrors());
        }

        // save accounting mapping
        if ($accountingSystem && $accountingId) {
            $this->saveAccountingMapping($existingRecord, $accountingSystem, $accountingId);
        }

        // If the payment does not have a customer then we need to
        // start a cash match job. This has to be done here because
        // the import tool does not create payment.updated events
        // that would start the cash match job.
        if ($this->matchmaker->shouldLookForMatches($existingRecord)) {
            $this->matchmaker->enqueue($existingRecord, true);
        }

        return new ImportRecordResult($existingRecord, ImportRecordResult::UPDATE);
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
     * Looks up a credit note by number.
     *
     * @throws ValidationException
     */
    private function lookupCreditNote(string $number): int
    {
        $creditNote = CreditNote::where('number', $number)->oneOrNull();
        if (!$creditNote) {
            throw new ValidationException('Could not find credit note: '.$number);
        }

        $this->creditNotes[$creditNote->id()] = $creditNote;

        return (int) $creditNote->id();
    }

    /**
     * Looks up an estimate by number.
     *
     * @throws ValidationException
     */
    private function lookupEstimate(string $number): int
    {
        $estimate = Estimate::where('number', $number)->oneOrNull();
        if (!$estimate) {
            throw new ValidationException('Could not find estimate: '.$number);
        }

        $this->estimates[$estimate->id()] = $estimate;

        return (int) $estimate->id();
    }

    /**
     * Looks up an invoice by number.
     *
     * @throws ValidationException
     */
    private function lookupInvoice(string $number): int
    {
        $invoice = Invoice::where('number', $number)->oneOrNull();
        if (!$invoice) {
            throw new ValidationException('Could not find invoice: '.$number);
        }

        $this->invoices[$invoice->id()] = $invoice;

        return (int) $invoice->id();
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
        // 4. randomly generated + payment method + payment reference

        $importIdentifier = array_value($record, 'customer.id');
        if (!$importIdentifier) {
            $importIdentifier = array_value($record, 'customer.number');
        }
        if (!$importIdentifier) {
            $importIdentifier = array_value($record, 'customer.name');
        }
        if (!$importIdentifier) {
            $importIdentifier = RandomString::generate();
        }

        // Always add payment method and reference # to end of identifier.
        // These might be empty.
        return $importIdentifier.'-'.array_value($record, 'method').'-'.array_value($record, 'reference');
    }
}
