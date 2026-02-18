<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Customer;
use App\Core\Database\TransactionManager;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\Traits\ImportHasCustomerTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Libs\ImportPaymentSource;

class PaymentSourceImporter extends BaseSpreadsheetImporter
{
    use ImportHasCustomerTrait;

    public function __construct(
        private ImportPaymentSource $importPaymentSource,
        TransactionManager $transactionManager
    ) {
        parent::__construct($transactionManager);
    }

    protected function findExistingRecord(array $record): ?Model
    {
        // Payment sources are always imported as an upsert operation, however,
        // the check for existing records is performed by the payment system.
        // It cannot be known here if there is an existing payment source until
        // the register source API call is made.
        return null;
    }

    protected function createRecord(array $record): ImportRecordResult
    {
        $customer = null;
        if (isset($record['customer'])) {
            $customer = $this->getCustomerObject($record['customer']);
            unset($record['customer']);
        }

        if (!($customer instanceof Customer)) {
            throw new RecordException('Missing customer');
        }

        try {
            // Determine merchant account to use
            if (isset($record['merchant_account_id'])) {
                $merchantAccount = $this->importPaymentSource->getMerchantAccountForId($record['merchant_account_id']);
                $record['gateway'] = $merchantAccount->gateway;
            } else {
                $merchantAccount = $this->importPaymentSource->getMerchantAccountForGateway($record['gateway'] ?? '');
            }

            $paymentSource = $this->importPaymentSource->import($customer, $record, $merchantAccount);
        } catch (PaymentSourceException $e) {
            throw new RecordException($e->getMessage());
        }

        return new ImportRecordResult($paymentSource, ImportRecordResult::CREATE);
    }
}
