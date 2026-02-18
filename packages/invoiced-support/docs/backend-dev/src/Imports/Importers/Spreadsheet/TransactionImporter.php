<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Models\Import;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\PaymentProcessing\Models\PaymentMethod;

/**
 * @deprecated
 */
class TransactionImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;

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

    private array $invoicesByNumber = [];

    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        $record = parent::buildRecord($mapping, $line, $options, $import);

        // look up invoice from invoice #
        $invoiceNumber = array_value($record, 'invoice_number');
        if ($invoiceNumber) {
            $record['invoice'] = $this->getInvoiceIdFromNumber($invoiceNumber);
            unset($record['invoice_number']);
            if (!$record['invoice']) {
                unset($record['invoice']);
            }
        }

        // record negative payments with an unknown type as refunds
        if (!isset($record['type']) && isset($record['amount']) && $record['amount'] < 0) {
            $record['type'] = Transaction::TYPE_REFUND;
            $record['amount'] = -$record['amount'];
        }

        // sanitize payment method
        if (isset($record['method'])) {
            $record['method'] = $this->parseMethod($record['method']);
        }

        return $record;
    }

    protected function findExistingRecord(array $record): ?Model
    {
        // Finding existing subscriptions is currently not supported.
        return null;
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        $transaction = new Transaction();
        if (isset($record['customer'])) {
            $customer = $this->getCustomerObject($record['customer']);
            if ($customer instanceof Customer) {
                $transaction->setCustomer($customer);
            }
            unset($record['customer']);
        }
        if (!$transaction->create($record)) {
            throw new RecordException('Could not create transaction: '.$transaction->getErrors());
        }

        return new ImportRecordResult($transaction, ImportRecordResult::CREATE);
    }

    public function parseMethod(string $str): string
    {
        $method = strtolower(str_replace(' ', '_', $str));

        // translation for UK users
        if ('cheque' == $method) {
            $method = PaymentMethod::CHECK;
        }

        if (!in_array($method, self::SUPPORTED_PAYMENT_METHODS)) {
            $method = PaymentMethod::OTHER;
        }

        return $method;
    }

    /**
     * Gets an invoice ID from a given invoice number.
     */
    public function getInvoiceIdFromNumber(string $number): ?int
    {
        if (empty($number)) {
            return null;
        }

        // Possible scenarios:
        // i) created in this import
        // ii) already created

        // i. check if invoice was created in this import
        if (isset($this->invoicesByNumber[$number])) {
            return $this->invoicesByNumber[$number]->id();

            // ii. lookup the invoice in the db
        } elseif ($invoice = Invoice::where('number', $number)->oneOrNull()) {
            $this->invoicesByNumber[$number] = $invoice;

            return (int) $invoice->id();
        }

        return null;
    }
}
