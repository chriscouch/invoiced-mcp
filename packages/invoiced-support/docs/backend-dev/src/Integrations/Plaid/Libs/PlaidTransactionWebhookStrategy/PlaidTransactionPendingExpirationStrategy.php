<?php

namespace App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy;

use App\CashApplication\Models\CashApplicationBankAccount;

class PlaidTransactionPendingExpirationStrategy implements PlaidTransactionWebhookStrategyInterface
{
    public function process(array $event, CashApplicationBankAccount $bankAccount): void
    {
        // mark that the user needs to go through update mode
        $bankAccount->plaid_link->needs_update = true;
        $bankAccount->plaid_link->save();
    }

    public function match(string $webhook_code): bool
    {
        return 'PENDING_EXPIRATION' === $webhook_code;
    }
}
