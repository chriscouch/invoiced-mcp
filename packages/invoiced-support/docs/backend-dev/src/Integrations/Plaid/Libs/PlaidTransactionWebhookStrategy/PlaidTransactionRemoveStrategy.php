<?php

namespace App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\CashApplication\Models\Payment;
use App\Core\Database\TransactionManager;

class PlaidTransactionRemoveStrategy implements PlaidTransactionWebhookStrategyInterface
{
    public function __construct(
        private readonly TransactionManager $transactionManager,
    ) {
    }

    public function process(array $event, CashApplicationBankAccount $bankAccount): void
    {
        foreach ($event['removed_transactions'] as $id) {
            $payment = Payment::where('external_id', $id)
                ->where('source', Payment::SOURCE_BANK_FEED)
                ->where('voided', false)
                ->oneOrNull();
            if ($payment) {
                $this->transactionManager->perform(function () use ($payment) {
                    $payment->void();
                });
            }
        }
    }

    public function match(string $webhook_code): bool
    {
        return 'TRANSACTIONS_REMOVED' === $webhook_code;
    }
}
