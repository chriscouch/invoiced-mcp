<?php

namespace App\PaymentProcessing\Reconciliation;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\ValueObjects\PendingCreateEvent;
use App\Core\Database\TransactionManager;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Enums\PayoutStatus;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\Payout;

class PayoutReconciler
{
    public function __construct(
        private TransactionManager $transactionManager,
        private MerchantAccountLedger $merchantAccountLedger,
        private EventSpool $eventSpool,
    ) {
    }

    public function reconcile(MerchantAccount $merchantAccount, array $parameters): Payout
    {
        return $this->transactionManager->perform(function () use ($merchantAccount, $parameters) {
            // Look for an existing payout
            $payout = Payout::where('merchant_account_id', $merchantAccount)
                ->where('reference', $parameters['reference'])
                ->oneOrNull();

            // Create or update the payout
            if ($payout) {
                $this->updatePayout($payout, $parameters);

                return $payout;
            }

            return $this->createPayout($parameters, $merchantAccount);
        });
    }

    private function createPayout(array $parameters, MerchantAccount $merchantAccount): Payout
    {
        $payout = new Payout();
        $payout->merchant_account = $merchantAccount;
        $payout->pending_amount = 0;
        foreach ($parameters as $key => $value) {
            $payout->$key = $value;
        }
        // Calculate net
        $payout->gross_amount = $payout->getAmount()->add($payout->getAmountPending())->toDecimal();
        $payout->saveOrFail();

        // Create balance transaction
        $transaction = new MerchantAccountTransaction();
        $transaction->merchant_account = $merchantAccount;
        $transaction->type = MerchantAccountTransactionType::Payout;
        $transaction->reference = $payout->reference;
        $transaction->currency = $payout->currency;
        $transaction->amount = -$payout->amount;
        $transaction->fee = 0;
        $transaction->net = -$payout->amount;
        $transaction->description = $payout->description;
        $transaction->available_on = $payout->initiated_at;
        $transaction->setSource($payout);
        $transaction->saveOrFail();

        $payout->merchant_account_transaction = $transaction;
        $payout->saveOrFail();

        // Sync the balance transaction with the ledger
        $ledger = $this->merchantAccountLedger->getLedger($merchantAccount);
        $this->merchantAccountLedger->syncTransaction($ledger, $transaction);

        // Create an activity log event
        $pendingEvent = new PendingCreateEvent($payout, EventType::PayoutCreated);
        $this->eventSpool->enqueue($pendingEvent);

        //link the old transaction to the payout if charged back
        $this->linkTransactionToChargeback($payout);

        return $payout;
    }

    private function updatePayout(Payout $payout, array $parameters): void
    {
        $originalStatus = $payout->status;

        foreach ($parameters as $key => $value) {
            // Do not set an empty bank account name (because it has been deleted)
            if ('bank_account_name' == $key && !$value) {
                continue;
            }

            $payout->$key = $value;
        }

        // Recalculate net
        $payout->gross_amount = $payout->getAmount()->add($payout->getAmountPending())->toDecimal();

        $payout->saveOrFail();

        // Handle a pending/completed -> failed/canceled transition
        if (in_array($originalStatus, [PayoutStatus::Pending, PayoutStatus::Completed]) && in_array($payout->status, [PayoutStatus::Canceled, PayoutStatus::Failed])) {
            $this->handleReversal($payout);
        }
    }

    private function handleReversal(Payout $payout): void
    {
        // Create balance transaction
        $transaction = new MerchantAccountTransaction();
        $transaction->merchant_account = $payout->merchant_account;
        $transaction->type = MerchantAccountTransactionType::PayoutReversal;
        $transaction->reference = $payout->reference.'-reversal';
        $transaction->currency = $payout->currency;
        $transaction->amount = $payout->amount;
        $transaction->fee = 0;
        $transaction->net = $payout->amount;
        $transaction->description = $payout->description;
        $transaction->available_on = $payout->initiated_at;
        $transaction->setSource($payout);
        $transaction->saveOrFail();

        // Sync the balance transaction with the ledger
        $ledger = $this->merchantAccountLedger->getLedger($payout->merchant_account);
        $this->merchantAccountLedger->syncTransaction($ledger, $transaction);

        // Create an activity log event
        $pendingEvent = new PendingCreateEvent($payout, EventType::PayoutFailed);
        $this->eventSpool->enqueue($pendingEvent);
    }

    private function linkTransactionToChargeback(Payout $payout): void
    {
        if(!$payout->modification_reference)
            return;

        $originalPayout = Payout::where('merchant_account_id', $payout->merchant_account)
            ->where('modification_reference', $payout->modification_reference)
            ->oneOrNull();

        if (!$originalPayout)
            return;

        $transaction = MerchantAccountTransaction::where('merchant_account_id', $payout->merchant_account)
            ->where('source_id', $originalPayout->id)
            ->where('source_type', ObjectType::fromModel($payout)->value)
            ->oneOrNull();

        if (!$transaction)
            return;

        $transaction->setSource($payout);
        $transaction->saveOrFail();
    }
}
