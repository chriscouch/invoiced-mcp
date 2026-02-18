<?php

namespace App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy;

use App\CashApplication\Models\CashApplicationBankAccount;

class PlaidTransactionLoginRepairedStrategy implements PlaidTransactionWebhookStrategyInterface
{
    public function process(array $event, CashApplicationBankAccount $bankAccount): void
    {
        // mark that the user no longer needs to go through update mode
        $bankAccount->plaid_link->needs_update = false;
        $bankAccount->plaid_link->save();
    }

    public function match(string $webhook_code): bool
    {
        return 'LOGIN_REPAIRED' === $webhook_code;
    }
}
