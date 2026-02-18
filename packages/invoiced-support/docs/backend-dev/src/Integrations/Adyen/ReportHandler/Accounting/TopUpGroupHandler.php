<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Models\MerchantAccount;

class TopUpGroupHandler extends AbstractGroupHandler
{
    public function handleRows(MerchantAccount $merchantAccount, string $identifier, array $rows): void
    {
        [$total, $fee, $feeDetails] = $this->getTotals($rows);

        // Check if the transaction already exists and move on if it does
        if ($this->checkIfExists($merchantAccount, $identifier, $total, $fee)) {
            return;
        }
        $merchantReference = $rows[0]['psp_payment_merchant_reference'];
        $dateRow = array_filter($rows, fn ($row) => $row['value_date']);
        $dateRow = reset($dateRow) ?: $rows[0];

        // Create or update the balance transaction
        $transaction = $this->saveTransaction(
            merchantAccount: $merchantAccount,
            identifier: $identifier,
            availableOn: $this->getValueDate($dateRow),
            type: MerchantAccountTransactionType::TopUp,
            description: 'Top Up',
            total: $total,
            fee: $fee,
            feeDetails: $feeDetails,
            merchantReference: $merchantReference
        );

        // Sync the balance transaction with the ledger
        $this->syncToLedger($merchantAccount, $transaction, $rows[0]);
    }


    protected function categorizeRow(array $row): array
    {
        $rowAmount = Money::fromDecimal($row['payment_currency'], $row['balance_pc']);
        $fee = Money::zero($row['payment_currency']);
        $feeDetails = [];

        if ($this->isLiableAccountHolder($row)) {
            // A negative amount against our liable account should be
            // ignored because this is our cost.
            // Any positive amount on the liable account contributes
            // to the gross payment amount.
            if ($row['balance_pc'] > 0) {
                $fee = $rowAmount;
                $feeDetails[] = [
                    'amount' => $fee->toDecimal(),
                    'type' => 'flywire_fee',
                ];
            }
        } else {
            $rowAmount = Money::fromDecimal($row['payment_currency'], $row['balance_pc'])->negated();
            $fee = Money::zero($row['payment_currency']);
        }


        return [$rowAmount, $fee, $feeDetails];
    }
}
