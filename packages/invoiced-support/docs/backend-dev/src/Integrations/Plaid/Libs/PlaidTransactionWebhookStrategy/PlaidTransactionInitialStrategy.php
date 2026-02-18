<?php

namespace App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Integrations\Plaid\Libs\PlaidTransactionProcessor;
use Carbon\CarbonImmutable;

class PlaidTransactionInitialStrategy implements PlaidTransactionWebhookStrategyInterface
{
    public function __construct(
        private readonly PlaidTransactionProcessor $processor
    ) {
    }

    public function process(array $event, CashApplicationBankAccount $bankAccount): void
    {
        // In the INITIAL_UPDATE webhook we can only go back to 30 days at most
        $start = CarbonImmutable::createFromTimestamp($bankAccount->data_starts_at);
        $start = $start->max(CarbonImmutable::now()->subDays(30));
        $end = CarbonImmutable::now();
        $this->processor->process($bankAccount, $start, $end);
    }

    public function match(string $webhook_code): bool
    {
        return 'INITIAL_UPDATE' === $webhook_code;
    }
}
