<?php

namespace App\Integrations\Adyen\ReportHandler;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\Exception\AdyenReconciliationException;
use App\Integrations\Adyen\Interfaces\ReportHandlerInterface;
use App\Integrations\Adyen\Operations\SaveAdyenPayout;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;

class PayoutReportHandler implements ReportHandlerInterface
{
    /** @var string[] */
    private array $transactionQueue = [];

    public function __construct(
        private readonly TenantContext $tenant,
        private SaveAdyenPayout $createPayout,
        private AdyenClient $adyenClient,
    ) {
    }

    public function handleRow(array $row): void
    {
        $storeId = $row['accountholder_reference'];
        $account = MerchantAccount::queryWithoutMultitenancyUnsafe()
            ->where('gateway_id', $storeId)
            ->oneOrNull();
        if (!$account) {
            return;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $company = $account->tenant();
        $this->tenant->runAs($company, function () use ($company, $row) {
            // use the company's time zone for date stuff
            $company->useTimezone();

            $category = $row['category'];
            if ('bank' == $category) {
                $this->handlePayoutRow($row);
                $this->transactionQueue = []; // reset the queue after encountering a payout
            } else {
                $this->handleOtherRow($row);
            }
        });
    }

    public function finish(): void
    {
        // Nothing to do in this handler
    }

    /**
     * @throws AdyenReconciliationException
     */
    private function handlePayoutRow(array $row): void
    {
        if (!SaveAdyenPayout::isPayout($row)) {
            return;
        }

        $storeReference = $row['store'] ?? null;
        $storeReference = $storeReference ?: $row['balanceaccount_reference']; // some older reports do not have store column enabled
        $merchantAccount = MerchantAccount::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $storeReference)
            ->oneOrNull();
        if (!$merchantAccount) {
            return;
        }

        // If the rolling balance is not zero then that means there is
        // a pending amount deducted from the payout amount. We track
        // this in order to have the gross payout amount match the associated transactions.
        $pendingAmount = Money::fromDecimal($row['currency'], $row['rolling_balance']);

        $payout = $this->createPayout->save($row['transfer_id'], $merchantAccount, $pendingAmount);
        if (!$payout) {
            return;
        }

        // Link the previously seen other transactions to the payout. Payouts are seen AFTER the transactions in the payout.
        foreach ($this->transactionQueue as $reference) {
            $transaction = MerchantAccountTransaction::where('merchant_account_id', $merchantAccount)
                ->where('reference', $reference)
                ->oneOrNull();
            if ($transaction) {
                $transaction->payout = $payout;
                $transaction->saveOrFail();
            }
        }
    }

    private function handleOtherRow(array $row): void
    {
        // When a non-payout transaction is encountered, add it to the queue so that it can be linked
        // to the next payout that we see.
        if ('platformPayment' == $row['category'] && 'capture' == $row['type']) {
            $this->transactionQueue[] = $row['psp_payment_psp_reference'];
        } elseif ('internal' == $row['category'] && 'internalTransfer' == $row['type']) {
            $this->transactionQueue[] = $row['transfer_id'];
        } else {
            // Load the transfer via the Transfers API
            try {
                $transfer = $this->adyenClient->getTransfer($row['transfer_id']);

                // Grab the PSP Modification Reference
                $pspModificationReference = $transfer['categoryData']['modificationPspReference'] ?? null;
                if ($pspModificationReference) {
                    $this->transactionQueue[] = $pspModificationReference;
                }
            } catch (IntegrationApiException) {
                // for now we're ignoring exceptions in this step
            }
        }
    }
}
