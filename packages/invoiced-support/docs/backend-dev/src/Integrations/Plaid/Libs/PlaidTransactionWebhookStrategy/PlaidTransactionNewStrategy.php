<?php

namespace App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Integrations\Plaid\Libs\PlaidTransactionProcessor;
use Carbon\CarbonImmutable;

class PlaidTransactionNewStrategy implements PlaidTransactionWebhookStrategyInterface
{
    public function __construct(
        private readonly PlaidTransactionProcessor $processor,
    ) {
    }

    public function process(array $event, CashApplicationBankAccount $bankAccount): void
    {
        // In DEFAULT_UPDATE we process data from the last data pull.
        // If we have not done INITIAL_UPDATE yet then this will do nothing.
        $lastRetrievedAt = $bankAccount->last_retrieved_data_at;
        if ($lastRetrievedAt > 0) {
            $start = CarbonImmutable::createFromTimestamp($lastRetrievedAt);
            $end = CarbonImmutable::now();
            $this->processor->process($bankAccount, $start, $end);
        }
    }

    public function match(string $webhook_code): bool
    {
        return 'DEFAULT_UPDATE' === $webhook_code;
    }
}
