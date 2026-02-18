<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Models\MerchantAccount;

class InternalTransferGroupHandler extends AbstractGroupHandler
{
    public function handleRows(MerchantAccount $merchantAccount, string $identifier, array $rows): void
    {
        // Calculate the transaction amounts
        [$total, $fee, $feeDetails] = $this->getTotals($rows);

        // Check if the transaction already exists and move on if it does
        if ($this->checkIfExists($merchantAccount, $identifier, $total, $fee)) {
            return;
        }

        // Create or update the balance transaction
        // TODO: If there are other transfer types, like a dispute fee
        // then that needs to be recognized here.
        $transaction = $this->saveTransaction(
            merchantAccount: $merchantAccount,
            identifier: $identifier,
            availableOn: $this->getValueDate($rows[0]),
            type: MerchantAccountTransactionType::Adjustment,
            description: $rows[0]['description'] ?: 'Adjustment',
            total: $total,
            fee: $fee,
            feeDetails: $feeDetails,
        );

        // Sync the balance transaction with the ledger
        $this->syncToLedger($merchantAccount, $transaction, $rows[0]);
    }

    protected function categorizeRow(array $row): array
    {
        $total = Money::fromDecimal($row['payment_currency'], $row['balance_pc']);
        $fee = Money::zero($row['payment_currency']);
        $feeDetails = [];

        return [$total, $fee, $feeDetails];
    }
}
