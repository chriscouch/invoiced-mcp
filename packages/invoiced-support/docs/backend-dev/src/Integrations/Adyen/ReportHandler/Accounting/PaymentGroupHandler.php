<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Operations\SaveAdyenPayment;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;

class PaymentGroupHandler extends AbstractGroupHandler
{
    public function __construct(
        private readonly PaymentFlowReconcile $paymentFlowReconcile,
        private readonly SaveAdyenPayment $saveAdyenPayment,
        MerchantAccountLedger $merchantAccountLedger,
        bool $adyenLiveMode
    )
    {
        parent::__construct($merchantAccountLedger, $adyenLiveMode);
    }

    public function handleRows(MerchantAccount $merchantAccount, string $identifier, array $rows): void
    {
        $hasSplit = false;
        foreach ($rows as $row) {
            if ('Seller split'=== $row['description']) {
                $hasSplit = true;
                break;
            }
        }

        // Calculate the transaction amounts
        /**
         * @var Money $total
         * @var Money $fee
         * @var array $feeDetails
         */
        [$total, $fee, $feeDetails] = $this->getTotals($rows);


        if ($total->equals($fee)) {
            // This is know to happen when partial transaction is only set in the report, this should be picked up by the next report
            // this is different from Acquring fee reversal because we have charge in the system
            if (!$hasSplit) {
                return;
            }
            throw new AdyenReconciliationException("Missed field INV-229 context", implode(array_map(fn($row) => $this->rowExceptionIdentifier($row) , $rows)));
        }

        $merchantReference = $rows[0]['psp_payment_merchant_reference'];
        try {
            if ($charge = $this->saveAdyenPayment->tryReconcile($identifier, $merchantReference, $total)) {
                // This is know to happen when partial transaction is only set in the report, this should be picked up by the next report
                // this is different from Acquring fee reversal because we have charge in the system
                if ($total->isZero() && $fee->greaterThan($total)) {
                    return;
                }
            }
        } catch (AdyenReconciliationException) {
            /** @var ?PaymentFlow $flow */
            $flow = PaymentFlow::where('identifier', $merchantReference)
                ->oneOrNull();
            $payment = null;
            if ($flow) {
                $payment = $this->paymentFlowReconcile->reconcile($flow, new PaymentFlowReconcileData(
                    gateway: AdyenGateway::ID,
                    status: ChargeValueObject::SUCCEEDED,
                    gatewayId: $identifier,
                    amount: $flow->getAmount()
                ));
            }
            $charge = $payment?->charge;
        }

        // Check if the transaction already exists and move on if it does
        if ($this->checkIfExists($merchantAccount, $identifier, $total, $fee)) {
            return;
        }

        $dateRow = array_filter($rows, fn ($row) => $row['value_date']);
        $dateRow = reset($dateRow) ?: $rows[0];

        // Create or update the balance transaction
        $transaction = $this->saveTransaction(
            merchantAccount: $merchantAccount,
            identifier: $identifier,
            availableOn: $this->getValueDate($dateRow),
            type: MerchantAccountTransactionType::Payment,
            description: $charge?->description ?: 'Payment',
            total: $total,
            fee: $fee,
            feeDetails: $feeDetails,
            source: $charge,
            merchantReference: $merchantReference
        );

        if ($charge) {
            $charge->merchant_account_transaction = $transaction;
            $charge->saveOrFail();
        }

        // Sync the balance transaction with the ledger
        $this->syncToLedger($merchantAccount, $transaction, $rows[0]);
    }

    protected function categorizeRow(array $row): array
    {
        $amount = 'received' === $row['status'] ? $row['received_pc'] : $row['balance_pc'];
        $rowAmount = Money::fromDecimal($row['payment_currency'], $amount);
        $total = Money::zero($row['payment_currency']);
        $fee = Money::zero($row['payment_currency']);
        $feeDetails = [];

        if ($this->isLiableAccountHolder($row)) {
            // A negative amount against our liable account should be
            // ignored because this is our cost.
            // Any positive amount on the liable account contributes
            // to the gross payment amount.
            if ($amount > 0) {
                $total = $rowAmount;
                $fee = $rowAmount;
                $feeDetails[] = [
                    'amount' => $fee->toDecimal(),
                    'type' => 'flywire_fee',
                ];
            }
        } elseif ($rowAmount->isNegative()) {
            // Fees on the merchant balance account are due to interchange++ Pricing
            $fee = $rowAmount->negated();

            $schemeFee = Money::fromDecimal($row['payment_currency'], (float) $row['platform_payment_scheme_fee']);
            $platformMarkup = Money::fromDecimal($row['payment_currency'], (float) $row['platform_payment_markup']);
            $platformCommission = Money::fromDecimal($row['payment_currency'], (float) $row['platform_payment_commission']);
            // We should not see platform fees here, however, in the rare case we do add them to scheme fees
            $totalScheme = $schemeFee->add($platformMarkup)->add($platformCommission);

            if (!$totalScheme->isZero()) {
                $feeDetails[] = [
                    'amount' => $totalScheme->negated()->toDecimal(),
                    'type' => 'scheme_fees',
                ];
            }

            $interchange = Money::fromDecimal($row['payment_currency'], (float) $row['platform_payment_interchange']);
            if (!$interchange->isZero()) {
                $feeDetails[] = [
                    'amount' => $interchange->negated()->toDecimal(),
                    'type' => 'interchange',
                ];
            }
        } else {
            $total = $rowAmount;
        }

        return [$total, $fee, $feeDetails];
    }
}
