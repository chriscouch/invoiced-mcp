<?php

namespace App\Integrations\Plaid\Libs;

use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Plaid\Models\PlaidItem;
use Carbon\CarbonImmutable;
use Generator;

class PlaidTransactionExtractor
{
    public function __construct(private PlaidApi $plaidApi)
    {
    }

    /**
     * Extracts transactions from a Plaid bank feed within a given start and end date.
     *
     * @throws IntegrationApiException
     */
    public function extract(PlaidItem $plaidItem, CarbonImmutable $start, CarbonImmutable $end): Generator
    {
        $transactions = [];
        $offset = 0;
        $perPage = 500;
        $done = false;

        while (!$done) {
            $response = $this->plaidApi->getTransactions($plaidItem, $start, $end, $perPage, $offset);
            foreach ($response->transactions as $transaction) {
                yield $transaction;
            }

            $offset += $perPage;
            $done = $offset >= $response->total_transactions;
        }

        return $transactions;
    }
}
