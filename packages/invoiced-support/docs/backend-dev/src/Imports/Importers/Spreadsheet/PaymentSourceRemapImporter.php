<?php

namespace App\Imports\Importers\Spreadsheet;

use App\Core\Database\TransactionManager;
use App\Core\Orm\Model;
use App\Imports\Exceptions\RecordException;
use App\Imports\ValueObjects\ImportRecordResult;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Libs\ImportPaymentSource;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentSource;

class PaymentSourceRemapImporter extends BaseSpreadsheetImporter
{
    public function __construct(
        private ImportPaymentSource $importPaymentSource,
        TransactionManager $transactionManager
    ) {
        parent::__construct($transactionManager);
    }

    protected function findExistingRecord(array $record): PaymentSource
    {
        // Look for an existing payment source. If one is not found then this will
        // produce an error.
        $type = $record['type'] ?? '';
        if (!in_array($type, ['card', 'bank_account'])) {
            throw new RecordException("Invalid source type. Allowed values are 'card', 'bank_account'");
        }

        $gatewayId = $record['current_gateway_id'];
        if (!$gatewayId) {
            throw new RecordException('Missing original gateway ID');
        }

        if ('bank_account' == $type) {
            $bankAccounts = BankAccount::where('gateway_id', $gatewayId)
                ->where('chargeable', true)
                ->first(2);
            if (0 == count($bankAccounts)) {
                throw new RecordException('Could not find an active bank account with gateway ID: '.$gatewayId);
            } elseif (count($bankAccounts) > 1) {
                throw new RecordException('Could not find a unique bank account with gateway ID: '.$gatewayId);
            }

            return $bankAccounts[0];
        }

        $cards = Card::where('gateway_id', $gatewayId)
            ->where('chargeable', true)
            ->first(2);
        if (0 == count($cards)) {
            throw new RecordException('Could not find an active card with gateway ID: '.$gatewayId);
        } elseif (count($cards) > 1) {
            throw new RecordException('Could not find a unique card with gateway ID: '.$gatewayId);
        }

        return $cards[0];
    }

    /**
     * @param PaymentSource $existingRecord
     */
    public function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        $customer = $existingRecord->customer;

        // Delete the original payment source
        $existingRecord->delete();

        // Create a new payment source using the values from the existing payment source
        $paymentSourceParams = $this->makeParams($existingRecord);

        // Apply new parameters
        if (isset($record['new_gateway_id'])) {
            $paymentSourceParams['gateway_id'] = $record['new_gateway_id'];
            $paymentSourceParams['gateway_customer'] = null; // always clear gateway customer if setting gateway ID
        }

        if (isset($record['new_gateway_customer'])) {
            $paymentSourceParams['gateway_customer'] = $record['new_gateway_customer'];
        }

        if (isset($record['account_number'])) {
            $paymentSourceParams['account_number'] = $record['account_number'];
        }

        try {
            // Determine merchant account to use
            if (isset($record['merchant_account_id'])) {
                $merchantAccount = $this->importPaymentSource->getMerchantAccountForId($record['merchant_account_id']);
                $paymentSourceParams['gateway'] = $merchantAccount->gateway;
            } else {
                $merchantAccount = $existingRecord->getMerchantAccount();
            }

            // Import the updated payment source as a new payment source
            $newPaymentSource = $this->importPaymentSource->import($customer, $paymentSourceParams, $merchantAccount);
        } catch (PaymentSourceException $e) {
            throw new RecordException($e->getMessage());
        }

        return new ImportRecordResult($newPaymentSource, ImportRecordResult::UPDATE);
    }

    private function makeParams(PaymentSource $paymentSource): array
    {
        $params = [
            'type' => $paymentSource->object,
            'gateway' => $paymentSource->gateway,
            'gateway_id' => $paymentSource->gateway_id,
            'gateway_customer' => $paymentSource->gateway_customer,
            'receipt_email' => $paymentSource->receipt_email,
        ];

        if ($paymentSource instanceof Card) {
            $params['brand'] = $paymentSource->brand;
            $params['last4'] = $paymentSource->last4;
            $params['exp_month'] = $paymentSource->exp_month;
            $params['exp_year'] = $paymentSource->exp_year;
            $params['funding'] = $paymentSource->funding;
        }

        if ($paymentSource instanceof BankAccount) {
            $params['last4'] = $paymentSource->last4;
            $params['bank_name'] = $paymentSource->bank_name;
            $params['routing_number'] = $paymentSource->routing_number;
            $params['country'] = $paymentSource->country;
            $params['currency'] = $paymentSource->currency;
        }

        return $params;
    }
}
