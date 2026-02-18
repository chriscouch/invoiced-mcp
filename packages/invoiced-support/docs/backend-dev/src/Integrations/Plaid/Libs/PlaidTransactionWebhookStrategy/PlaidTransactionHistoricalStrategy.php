<?php

namespace App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Integrations\Plaid\Libs\PlaidTransactionProcessor;
use Carbon\CarbonImmutable;

class PlaidTransactionHistoricalStrategy implements PlaidTransactionWebhookStrategyInterface
{
    public function __construct(
        private readonly PlaidTransactionProcessor $processor
    ) {
    }

    public function process(array $event, CashApplicationBankAccount $bankAccount): void
    {
        // In the HISTORICAL_UPDATE webhook we go from the start date up to 30 days ago
        $start = CarbonImmutable::createFromTimestamp($bankAccount->data_starts_at);
        $end = CarbonImmutable::now()->subDays(30);
        if ($start->isBefore($end)) {
            $this->processor->process($bankAccount, $start, $end);
        }
    }

    public function match(string $webhook_code): bool
    {
        return 'HISTORICAL_UPDATE' === $webhook_code;
    }
}
