<?php

namespace App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy;

use App\CashApplication\Models\CashApplicationBankAccount;

interface PlaidTransactionWebhookStrategyInterface
{
    public function process(array $event, CashApplicationBankAccount $bankAccount): void;

    public function match(string $webhook_code): bool;
}
