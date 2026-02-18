<?php

namespace App\Integrations\Adyen\ReportHandler;

use App\Core\Database\TransactionManager;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Interfaces\AccountingReportGroupHandlerInterface;
use App\Integrations\Adyen\Interfaces\ReportHandlerInterface;
use App\Integrations\Adyen\Operations\SaveAdyenPayout;
use App\Integrations\Adyen\ReportHandler\Accounting\ChargebackGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\ChargebackReversalGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\InternalTransferGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\PaymentGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\PaymentReversalGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\PayoutGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\RefundGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\RefundReversalGroupHandler;
use App\Integrations\Adyen\ReportHandler\Accounting\TopUpGroupHandler;
use App\PaymentProcessing\Models\MerchantAccount;
use Carbon\CarbonImmutable;

class AccountingReportHandler implements ReportHandlerInterface
{
    use HasMerchantAccountTrait;

    private array $chargebacks = [];
    private array $chargebackReversals = [];
    private array $internalTransfers = [];
    private array $payments = [];
    private array $payouts = [];
    private array $refunds = [];
    private array $refundReversals = [];
    private array $topUps = [];
    private array $paymentReversals = [];

    public function __construct(
        private readonly TenantContext                  $tenant,
        private readonly bool                           $adyenLiveMode,
        private readonly TransactionManager             $transactionManager,
        private readonly ChargebackGroupHandler         $chargebackGroupHandler,
        private readonly ChargebackReversalGroupHandler $chargebackReversalGroupHandler,
        private readonly InternalTransferGroupHandler   $internalTransferGroupHandler,
        private readonly PaymentGroupHandler            $paymentGroupHandler,
        private readonly PayoutGroupHandler             $payoutGroupHandler,
        private readonly RefundGroupHandler             $refundGroupHandler,
        private readonly RefundReversalGroupHandler     $refundReversalGroupHandler,
        private readonly TopUpGroupHandler              $topUpGroupHandler,
        private readonly PaymentReversalGroupHandler    $paymentReversalGroupHandler,
    ) {
    }

    public function handleRow(array $row): void
    {
        $category = $row['category'];
        if ('platformPayment' == $category) {
            $this->handlePaymentRow($row);
        } elseif ('bank' == $category) {
            $this->handleBankRow($row);
        } elseif ('internal' == $category) {
            //we ignore manual corrections prior this date, because they correspond either Invoiced accounts or chargebacks
            if ('manualCorrection' == $row['type'] && (new CarbonImmutable($row['value_date']))->lessThan(new CarbonImmutable('2025-08-19'))) {
                return;
            }
            $this->handleInternalRow($row);
        } elseif ('topUp' == $category) {
            $this->handleTopUpRow($row);
        } else {
            throw $this->unsupportedRow($row);
        }
    }

    public function finish(): void
    {
        // Process all the payment records
        foreach ($this->payments as $pspReference => $rows) {
            $this->handleRows($pspReference, $rows, $this->paymentGroupHandler);
        }

        // Process all the internal transfers
        foreach ($this->internalTransfers as $transferId => $rows) {
            $this->handleRows($transferId, $rows, $this->internalTransferGroupHandler);
        }

        // Process all the refunds records
        foreach ($this->refunds as $pspModificationReference => $rows) {
            $this->handleRows($pspModificationReference, $rows, $this->refundGroupHandler);
        }

        // Process all the refund reversal records
        foreach ($this->refundReversals as $pspModificationReference => $rows) {
            $this->handleRows($pspModificationReference, $rows, $this->refundReversalGroupHandler);
        }

        // Process all the chargeback records
        foreach ($this->chargebacks as $pspModificationReference => $rows) {
            $this->handleRows($pspModificationReference, $rows, $this->chargebackGroupHandler);
        }

        // Process all the chargeback reversal records
        foreach ($this->chargebackReversals as $pspModificationReference => $rows) {
            $this->handleRows($pspModificationReference, $rows, $this->chargebackReversalGroupHandler);
        }

        // Process all the payout records last
        foreach ($this->payouts as $transferId => $rows) {
            $this->handleRows($transferId, $rows, $this->payoutGroupHandler);
        }

        // Process all the topUps records last
        foreach ($this->topUps as $transferId => $rows) {
            $this->handleRows($transferId, $rows, $this->topUpGroupHandler);
        }

        // Process all the payment reversal records
        foreach ($this->paymentReversals as $transferId => $rows) {
            $this->handleRows($transferId, $rows, $this->paymentReversalGroupHandler);
        }
    }

    /**
     * Handles a row in the report with category = bank.
     *
     * @throws AdyenReconciliationException
     */
    private function handleBankRow(array $row): void
    {
        if (SaveAdyenPayout::isPayout($row)) {
            $this->payouts[$row['transfer_id']] = [$row];
        }
    }

    /**
     * Handles a row in the report with category = internal.
     *
     * @throws AdyenReconciliationException
     */
    private function handleInternalRow(array $row): void
    {
        // Ignore internal transactions on our liable account
        if ($this->isLiableAccountHolder($row)) {
            return;
        }

        if ('internalTransfer' == $row['type']) {
            if ('booked' == $row['status']) {
                $this->internalTransfers[$row['transfer_id']] = [$row];
            }
        } else {
            throw $this->unsupportedRow($row);
        }
    }

    /**
     * Handles a row in the report with category = platformPayment.
     *
     * @throws AdyenReconciliationException
     */
    private function handlePaymentRow(array $row): void
    {
        $type = $row['type'];
        if ('capture' == $type) {
            $this->handlePaymentCaptureRow($row);
        } elseif (in_array($type, ['chargeback', 'secondChargeback'])) {
            $this->handlePaymentChargebackRow($row);
        } elseif ('chargebackReversal' == $type) {
            $this->handlePaymentChargebackReversalRow($row);
        } elseif ('refund' == $type) {
            $this->handlePaymentRefundRow($row);
        } elseif ('refundReversal' == $type) {
            $this->handlePaymentRefundReversalRow($row);
        } elseif ('captureReversal' == $type) {
            $this->handlePaymentPaymentReversalRow($row);
        } else {
            throw $this->unsupportedRow($row);
        }
    }

    /**
     * Handles a row in the report with:
     *   category = platformPayment
     *   type = capture
     */
    private function handlePaymentCaptureRow(array $row): void
    {
        $status = $row['status'];
        if ('captured' !== $status && 'received' !== $status) {
            return;
        }

        $pspReference = $row['psp_payment_psp_reference'];
        if (!isset($this->payments[$pspReference])) {
            $this->payments[$pspReference] = [];
        }

        //duplicate row
        $iterate = $this->payments[$pspReference];
        foreach ($iterate as $key => $paymentRow) {
            if ($paymentRow['description'] === $row['description'] && $paymentRow['amount'] === $row['amount']) {
                //we give priority to capture status, because it has value date
                if ('received' === $paymentRow['status']) {
                    $this->payments[$pspReference][$key] = $row;
                }

                return;
            }
        }

        $this->payments[$pspReference][] = $row;
    }

    /**
     * Handles a row in the report with:
     *   category = platformPayment
     *   type = chargeback, secondChargeback
     *
     * @throws AdyenReconciliationException
     */
    private function handlePaymentChargebackRow(array $row): void
    {
        $status = $row['status'];
        if (!in_array($status, ['chargeback', 'secondChargeback'])) {
            return;
        }

        // Ignore chargeback transactions on our liable account
        if ($this->isLiableAccountHolder($row)) {
            return;
        }

        $pspModificationReference = $row['psp_modification_psp_reference'];
        if (!isset($this->chargebacks[$pspModificationReference])) {
            $this->chargebacks[$pspModificationReference] = [];
        }

        $this->chargebacks[$pspModificationReference][] = $row;
    }

    /**
     * Handles a row in the report with:
     *   category = platformPayment
     *   type = chargebackReversal
     *
     * @throws AdyenReconciliationException
     */
    private function handlePaymentChargebackReversalRow(array $row): void
    {
        $status = $row['status'];
        if ('chargebackReversed' != $status) {
            return;
        }

        // Ignore chargeback transactions on our liable account
        if ($this->isLiableAccountHolder($row)) {
            return;
        }

        $pspModificationReference = $row['psp_modification_psp_reference'];
        if (!isset($this->chargebackReversals[$pspModificationReference])) {
            $this->chargebackReversals[$pspModificationReference] = [];
        }

        $this->chargebackReversals[$pspModificationReference][] = $row;
    }

    /**
     * Handles a row in the report with:
     *   category = platformPayment
     *   type = refund
     */
    private function handlePaymentRefundRow(array $row): void
    {
        $status = $row['status'];
        if ('refunded' != $status) {
            return;
        }

        $pspModificationReference = $row['psp_modification_psp_reference'];
        if (!isset($this->refunds[$pspModificationReference])) {
            $this->refunds[$pspModificationReference] = [];
        }

        $this->refunds[$pspModificationReference][] = $row;
    }

    /**
     * Handles a row in the report with:
     *   category = platformPayment
     *   type = refundReversal
     */
    private function handlePaymentRefundReversalRow(array $row): void
    {
        $status = $row['status'];
        if ('refundReversed' != $status) {
            return;
        }

        $pspModificationReference = $row['psp_modification_psp_reference'];
        if (!isset($this->refundReversals[$pspModificationReference])) {
            $this->refundReversals[$pspModificationReference] = [];
        }

        $this->refundReversals[$pspModificationReference][] = $row;
    }

    /**
     * Handles a row in the report with:
     *   category = platformPayment
     *   type = captireReversal
     *
     * @throws AdyenReconciliationException
     */
    private function handlePaymentPaymentReversalRow(array $row): void
    {
        $status = $row['status'];
        if (!in_array($status, ['captureReversed'])) {
            return;
        }

        $pspModificationReference = $row['psp_modification_psp_reference'];
        if (!isset($this->paymentReversals[$pspModificationReference])) {
            $this->paymentReversals[$pspModificationReference] = [];
        }

        $this->paymentReversals[$pspModificationReference][] = $row;
    }

    private function getMerchantAccountFromRows(array $rows): ?MerchantAccount
    {
        // Look for the first row with a value
        foreach ($rows as $row) {
            if ($gatewayId = $this->getStoreReference($row)) {
                return $this->getMerchantAccount($gatewayId);
            }
        }

        return null;
    }

    private function getStoreReference(array $row): string
    {
        $storeReference = $row['store'] ?? null;

        return $storeReference ?: $row['balance_account_reference']; // some older reports do not have store column enabled
    }

    /**
     * Checks if a row belongs to the liable account holder.
     */
    private function isLiableAccountHolder(array $row): bool
    {
        return $row['accountholder'] == AdyenConfiguration::getLiableAccountHolder($this->adyenLiveMode);
    }

    private function rowExceptionIdentifier(array $row): string
    {
        return 'Transfer ID: '.$row['transfer_id'].' Category: '.$row['category'].' Type: '.$row['type'].' Status: '.$row['status'];
    }

    private function unsupportedRow(array $row): AdyenReconciliationException
    {
        return new AdyenReconciliationException('Encountered unsupported transaction accounting report.', $this->rowExceptionIdentifier($row));
    }

    /**
     * @throws AdyenReconciliationException
     */
    private function handleRows(string $identifier, array $rows, AccountingReportGroupHandlerInterface $groupHandler): void
    {
        $merchantAccount = $this->getMerchantAccountFromRows($rows);
        if (!$merchantAccount) {
            return;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $company = $merchantAccount->tenant();
        $this->tenant->runAs($company, function () use ($merchantAccount, $company, $groupHandler, $identifier, $rows) {
            // use the company's time zone for date stuff
            $company->useTimezone();

            $this->transactionManager->perform(function () use ($merchantAccount, $groupHandler, $identifier, $rows) {
                $groupHandler->handleRows($merchantAccount, $identifier, $rows);
            });
        });
    }

    private function handleTopUpRow(array $row): void
    {
        $status = $row['status'];
        if ('captured' != $status) {
            return;
        }

        $pspReference = $row['psp_payment_psp_reference'];
        if (!isset($this->topUps[$pspReference])) {
            $this->topUps[$pspReference] = [];
        }

        $this->topUps[$pspReference][] = $row;
    }
}
