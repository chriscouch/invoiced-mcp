<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Models\MerchantAccount;

class ChargebackReversalGroupHandler extends AbstractGroupHandler
{
    public function handleRows(MerchantAccount $merchantAccount, string $identifier, array $rows): void
    {
        // Calculate the transaction amounts
        [$total, $fee, $feeDetails] = $this->getTotals($rows);

        // Check if the transaction already exists and move on if it does
        if ($this->checkIfExists($merchantAccount, $identifier, $total, $fee)) {
            return;
        }

        // Look for an existing dispute to associate with
        $firstRow = $rows[0];
        $charge = Charge::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $firstRow['psp_payment_psp_reference'])
            ->oneOrNull();
        $dispute = null;
        if ($charge) {
            $dispute = Dispute::where('charge_id', $charge)
                ->sort('id ASC')
                ->oneOrNull();
        }

        // Create or update the balance transaction
        $transaction = $this->saveTransaction(
            merchantAccount: $merchantAccount,
            identifier: $identifier,
            availableOn: $this->getValueDate($firstRow),
            type: MerchantAccountTransactionType::DisputeReversal,
            description: 'Dispute Reversal',
            total: $total,
            fee: $fee,
            feeDetails: $feeDetails,
            source: $dispute,
        );

        // Sync the balance transaction with the ledger
        $this->syncToLedger($merchantAccount, $transaction, $firstRow);
    }

    protected function categorizeRow(array $row): array
    {
        $rowAmount = Money::fromDecimal($row['payment_currency'], $row['balance_pc']);
        $total = Money::zero($row['payment_currency']);
        $fee = Money::zero($row['payment_currency']);
        $feeDetails = [];

        if (str_starts_with($row['description'], 'ChargebackReversal Fee')) {
            $fee = $rowAmount;
            $feeDetails[] = [
                'amount' => $fee->negated()->toDecimal(),
                'type' => 'flywire_fee',
            ];
        } elseif (!$this->isLiableAccountHolder($row)) {
            // Any amount against our liable account should be ignored.
            $total = $rowAmount;
        }

        return [$total, $fee, $feeDetails];
    }
}
