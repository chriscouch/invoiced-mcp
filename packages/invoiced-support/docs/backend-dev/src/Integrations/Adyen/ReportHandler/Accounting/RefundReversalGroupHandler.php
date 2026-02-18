<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\Refund;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\Operations\VoidRefund;

class RefundReversalGroupHandler extends AbstractGroupHandler
{
    public function __construct(
        MerchantAccountLedger $merchantAccountLedger,
        private VoidRefund $voidRefund,
        bool $adyenLiveMode,
    ) {
        parent::__construct($merchantAccountLedger, $adyenLiveMode);
    }
    public function handleRows(MerchantAccount $merchantAccount, string $identifier, array $rows): void
    {
        // Calculate the transaction amounts
        /** @var Money $rounding */
        [$total, $fee, $feeDetails, $rounding] = $this->getTotals($rows);
        // Check if the transaction already exists and move on if it does
        if ($this->checkIfExists($merchantAccount, $identifier, $total, $fee)) {
            return;
        }

        // Look for an existing refund to associate with
        $refund = Refund::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $identifier)
            ->oneOrNull();


        $description = 'Refund Reversal';

        if ($rounding->isPositive()) {
            $description .= ' (' .$rounding->toDecimal() . ' Rounding Adjustment)';
        }

        // Create or update the balance transaction
        $transaction = $this->saveTransaction(
            merchantAccount: $merchantAccount,
            identifier: $identifier,
            availableOn: $this->getValueDate($rows[0]),
            type: MerchantAccountTransactionType::RefundReversal,
            description: $description,
            total: $total,
            fee: $fee,
            feeDetails: $feeDetails,
            source: $refund,
        );
        
        if ($refund && $refund->status !== RefundValueObject::VOIDED) {
            $refund->merchant_account_transaction = $transaction;
            $this->voidRefund->void($refund);
        }

        // Sync the balance transaction with the ledger
        $this->syncToLedger($merchantAccount, $transaction, $rows[0]);
    }

    protected function categorizeRow(array $row): array
    {
        $rowAmount = Money::fromDecimal($row['payment_currency'], $row['balance_pc']);
        $total = Money::zero($row['payment_currency']);
        $fee = Money::zero($row['payment_currency']);
        $rounding = Money::zero($row['payment_currency']);
        $feeDetails = [];

        if ($this->isLiableAccountHolder($row)) {
            // "Variable Fee" and "Fixed Fee" is a return of the original fee
            // to the merchant. Any other negative amount against our liable
            // account should be ignored because this is our cost. Any other
            // positive amount against our liable account is unexpected and
            // needs to be investigated.
            if (in_array($row['description'], ['Variable Fee', 'Fixed Fee'])) {
                $total = $rowAmount;
                $fee = $rowAmount;
                $feeDetails[] = [
                    'amount' => $fee->toDecimal(),
                    'type' => 'flywire_fee',
                ];
            } elseif ($row['balance_pc'] < 0) {
                if (str_starts_with($row['description'], 'Remainder Fee for')) {
                    $total = $rowAmount;
                    $rounding = $rounding->add($rowAmount);
                } else {
                    throw new AdyenReconciliationException('Unexpected negative amount in liable account for refund reversal.', $this->rowExceptionIdentifier($row));
                }
            }
        } else {
            $total = $rowAmount;
        }

        return [$total, $fee, $feeDetails, $rounding];
    }
}
