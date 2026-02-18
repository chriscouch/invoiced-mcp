<?php

namespace App\Integrations\Adyen\ReportHandler\Accounting;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Mailer\Mailer;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\MerchantAccountTransactionNotification;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;

class PaymentReversalGroupHandler extends AbstractGroupHandler
{
    public function __construct(
        MerchantAccountLedger   $merchantAccountLedger,
        bool                    $adyenLiveMode,
        private readonly Mailer $mailer,
    ) {
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

        // Create or update the balance transaction
        $transaction = $this->saveTransaction(
            merchantAccount: $merchantAccount,
            identifier: $identifier,
            availableOn: $this->getValueDate($rows[0]),
            type: MerchantAccountTransactionType::Adjustment,
            description: 'CaptureReversal',
            total: $total,
            fee: $fee,
            feeDetails: $feeDetails,
            source: $charge,
        );

        // Sync the balance transaction with the ledger
        $this->syncToLedger($merchantAccount, $transaction, $rows[0]);

        //Send slack notification on CaptureReversal
        $this->mailer->send([
            'from_email' => 'no-reply@invoiced.com',
            'to' => [['email' => 'b2b-payfac-notificati-aaaaqfagorxgbzwrnrb7unxgrq@flywire.slack.com', 'name' => 'Payment Reversal Row from report']],
            'subject' => "Payout Reconciliation - CaptureReversal - {$rows[0]['account_holder_description']}",
            'text' => "Payout Reconciliation - CaptureReversal\nTenant ID: {$merchantAccount->tenant_id}\nPSP Reference: {$rows[0]['psp_payment_psp_reference']}",
        ]);

        // Add to notifications tracking
        $this->addTransactionNotification($transaction);
    }

    protected function categorizeRow(array $row): array
    {
        $rowAmount = Money::fromDecimal($row['payment_currency'], $row['balance_pc']);
        $total = Money::zero($row['payment_currency']);
        $fee = Money::zero($row['payment_currency']);
        $feeDetails = [];

        if ($this->isLiableAccountHolder($row)) {
            // A positive amount against our liable account should be
            // ignored because this is our cost.
            // Any negative amount on the liable account contributes
            // to the gross payment amount.
            if ($rowAmount->isNegative()) {
                $total = $rowAmount;
                $fee = $rowAmount;
                $feeDetails[] = [
                    'amount' => $fee->negated()->toDecimal(),
                    'type' => 'flywire_fee',
                ];
            }
        } elseif ($rowAmount->isNegative()) {
            $total = $rowAmount;
        } else {
            // Fees on the merchant balance account are due to interchange++ Pricing
            $fee = $rowAmount->negated();

            $schemeFee = Money::fromDecimal($row['payment_currency'],(float) $row['platform_payment_scheme_fee']);
            $platformMarkup = Money::fromDecimal($row['payment_currency'],(float) $row['platform_payment_markup']);
            $platformCommission = Money::fromDecimal($row['payment_currency'],(float) $row['platform_payment_commission']);
            // We should not see platform fees here, however, in the rare case we do add them to scheme fees
            $totalScheme = $schemeFee->add($platformMarkup)->add($platformCommission);

            if (!$totalScheme->isZero()) {
                $feeDetails[] = [
                    'amount' => $totalScheme->toDecimal(),
                    'type' => 'scheme_fees',
                ];
            }

            $interchange = Money::fromDecimal($row['payment_currency'],(float) $row['platform_payment_interchange']);
            if (!$interchange->isZero()) {
                $feeDetails[] = [
                    'amount' => $interchange->toDecimal(),
                    'type' => 'interchange',
                ];
            }
        }

        return [$total, $fee, $feeDetails];
    }

    private function addTransactionNotification(MerchantAccountTransaction $transaction): void
    {
        $merchantTransactionNotification = new MerchantAccountTransactionNotification();
        $merchantTransactionNotification->merchant_account_transaction = $transaction;
        $merchantTransactionNotification->saveOrFail();
    }
}
