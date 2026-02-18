<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Ledger\Exception\LedgerException;
use App\Core\Orm\Model;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Interfaces\AccountingReportGroupHandlerInterface;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;

abstract class AbstractGroupHandler implements AccountingReportGroupHandlerInterface
{
    public function __construct(
        protected MerchantAccountLedger $merchantAccountLedger,
        protected bool $adyenLiveMode,
    ) {
    }

    protected function checkIfExists(MerchantAccount $merchantAccount, string $identifier, Money $total, Money $fee): bool
    {
        return MerchantAccountTransaction::where('merchant_account_id', $merchantAccount)
            ->where('reference', $identifier)
            ->where('amount', $total->toDecimal())
            ->where('fee', $fee->toDecimal())
            ->count() > 0;
    }

    /**
     * Creates or updates a merchant account transaction.
     */
    protected function saveTransaction(MerchantAccount $merchantAccount, string $identifier, CarbonImmutable $availableOn, MerchantAccountTransactionType $type, string $description, Money $total, Money $fee, array $feeDetails, ?Model $source = null, ?string $merchantReference = null): MerchantAccountTransaction
    {
        // Check for an existing transaction and update only properties that might have changed
        $transaction = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount)
            ->where('reference', $identifier)
            ->oneOrNull();
        if ($transaction) {
            $transaction->amount = $total->toDecimal();
            $transaction->fee = $fee->toDecimal();
            $transaction->fee_details = $feeDetails;
            $transaction->net = $total->subtract($fee)->toDecimal();
            $transaction->setSource($source);
            $transaction->saveOrFail();

            return $transaction;
        }

        // Create a new transaction
        $transaction = new MerchantAccountTransaction();
        $transaction->merchant_account = $merchantAccount;
        $transaction->type = $type;
        $transaction->reference = $identifier;
        $transaction->currency = $total->currency;
        $transaction->amount = $total->toDecimal();
        $transaction->fee = $fee->toDecimal();
        $transaction->fee_details = $feeDetails;
        $transaction->net = $total->subtract($fee)->toDecimal();
        $transaction->description = $description;
        $transaction->available_on = $availableOn;
        $transaction->merchant_reference = $merchantReference;
        $transaction->setSource($source);
        $transaction->saveOrFail();

        return $transaction;
    }

    /**
     * Gets the value date from a row.
     */
    protected function getValueDate(array $row): CarbonImmutable
    {
        return new CarbonImmutable($row['value_date'] ?: $row['booking_date'], new CarbonTimeZone($row['value_date_timezone'] ?: $row['booking_date_timezone']));
    }

    /**
     * Checks if a row belongs to the liable account holder.
     */
    protected function isLiableAccountHolder(array $row): bool
    {
        return $row['accountholder'] == AdyenConfiguration::getLiableAccountHolder($this->adyenLiveMode);
    }

    protected function rowExceptionIdentifier(array $row): string
    {
        return 'Transfer ID: '.$row['transfer_id'].' Category: '.$row['category'].' Type: '.$row['type'].' Status: '.$row['status'];
    }

    /**
     * @throws AdyenReconciliationException
     */
    protected function syncToLedger(MerchantAccount $merchantAccount, MerchantAccountTransaction $transaction, array $row): void
    {
        try {
            $ledger = $this->merchantAccountLedger->getLedger($merchantAccount);
            $this->merchantAccountLedger->syncTransaction($ledger, $transaction);
        } catch (LedgerException $e) {
            throw new AdyenReconciliationException($e->getMessage(), $this->rowExceptionIdentifier($row));
        }
    }

    /**
     * @throws AdyenReconciliationException
     */
    protected function getTotals(array $rows): array
    {
        $total = Money::zero($rows[0]['payment_currency']);
        $fee = Money::zero($rows[0]['payment_currency']);
        $rounding = Money::zero($rows[0]['payment_currency']);
        $feeDetails = [];
        foreach ($rows as $row) {
            //array pad to ensure we always have optional 4th argument
            [$rowTotal, $rowFee, $rowFeeDetails, $rowRounding] = array_pad($this->categorizeRow($row), 4,Money::zero($rows[0]['payment_currency']));
            $total = $total->add($rowTotal);
            $fee = $fee->add($rowFee);
            $rounding = $rounding->add($rowRounding);
            $feeDetails = array_merge($feeDetails, $rowFeeDetails);
        }

        return [$total, $fee, $feeDetails, $rounding];
    }

    /**
     * @throws AdyenReconciliationException
     */
    protected function categorizeRow(array $row): array
    {
        throw new AdyenReconciliationException('Not implemented', '');
    }
}
