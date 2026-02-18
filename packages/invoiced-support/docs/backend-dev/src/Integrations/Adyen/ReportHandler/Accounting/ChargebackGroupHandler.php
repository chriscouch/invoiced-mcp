<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\Enums\ChargebackEvent;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\Dispute;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use App\PaymentProcessing\Reconciliation\DisputeReconciler;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;

class ChargebackGroupHandler extends AbstractGroupHandler
{
    public function __construct(private readonly UpdateChargeStatus $updateChargeStatus,
                                MerchantAccountLedger               $merchantAccountLedger,
                                bool                                $adyenLiveMode,
                                private readonly DisputeReconciler  $disputeReconciler)
    {
        parent::__construct($merchantAccountLedger, $adyenLiveMode);
    }

    public function handleRows(MerchantAccount $merchantAccount, string $identifier, array $rows): void
    {
        // Calculate the transaction amounts
        [$total, $fee, $feeDetails] = $this->getTotals($rows);

        // Check if the transaction already exists and move on if it does
        if ($this->checkIfExists($merchantAccount, $identifier, $total, $fee)) {
            return;
        }

        /** @var ?Charge $charge */
        $charge = Charge::where('gateway_id', $rows[0]['psp_payment_psp_reference'])
            ->where('gateway', AdyenGateway::ID)
            ->oneOrNull();
        $dispute = null;
        $reason = 'Dispute';
        if ($charge && 'bank_account' === $charge->payment_source_type) {
            $reason = 'Direct Debit Chargeback';

            if (Charge::FAILED !== $charge->status) {
                $this->updateChargeStatus->saveStatus($charge, Charge::FAILED, $reason);
            }
        } else {
            $amount = new Money($rows[0]['currency'], $rows[0]['amount']);
            $status = $rows[0]['status'] ?? '';
            $event = ChargebackEvent::fromReportRowStatus($status);

            $parameters = [
                'charge_gateway_id' => $rows[0]['psp_payment_psp_reference'] ?? null,
                'gateway_id' => $identifier,
                'gateway' => AdyenGateway::ID,
                'currency' => $rows[0]['currency'],
                'amount' => $amount->toDecimal(),
                'status' => $event ? $event->toDisputeStatus() : DisputeStatus::Unresponded,
                'reason' => $rows[0]['description'] ?? $rows[0]['category'] ?? null,
            ];

            $dispute = $this->disputeReconciler->reconcile($parameters);
            if (!$dispute)
                $dispute = Dispute::where('gateway', AdyenGateway::ID)
                    ->where('gateway_id', $identifier)
                    ->oneOrNull();
        }

        // Create or update the balance transaction
        $transaction = $this->saveTransaction(
            merchantAccount: $merchantAccount,
            identifier: $identifier,
            availableOn: $this->getValueDate($rows[0]),
            type: MerchantAccountTransactionType::Dispute,
            description: $reason,
            total: $total,
            fee: $fee,
            feeDetails: $feeDetails,
            source: $dispute ?? $charge,
        );

        if ($dispute) {
            $dispute->merchant_account_transaction = $transaction;
            $dispute->saveOrFail();
        }

        // Sync the balance transaction with the ledger
        $this->syncToLedger($merchantAccount, $transaction, $rows[0]);
    }

    protected function categorizeRow(array $row): array
    {
        $rowAmount = Money::fromDecimal($row['payment_currency'], $row['balance_pc']);
        $total = Money::zero($row['payment_currency']);
        $fee = Money::zero($row['payment_currency']);
        $feeDetails = [];

        if ($this->isLiableAccountHolder($row)) {
            // Any negative amount against our liable
            // account should be ignored because this is our cost. Any other
            // positive amount against our liable account is unexpected and
            // needs to be investigated.
            if ($row['balance_pc'] > 0) {
                throw new AdyenReconciliationException('Unexpected positive amount in liable account for chargeback.', $this->rowExceptionIdentifier($row));
            }
        } elseif (str_starts_with($row['description'], 'Chargeback Fee') || str_starts_with($row['description'], 'SecondChargeback Fee')) {
            $fee = $rowAmount;
            $feeDetails[] = [
                'amount' => $fee->negated()->toDecimal(),
                'type' => 'flywire_fee',
            ];
        } else {
            $total = $rowAmount;
        }

        return [$total, $fee, $feeDetails];
    }
}
