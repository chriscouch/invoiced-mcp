<?php

namespace App\Integrations\Plaid\Libs;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\CashApplication\Operations\CreateBankFeedTransaction;
use App\Core\Orm\Exception\ModelException;
use App\Integrations\Exceptions\IntegrationApiException;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class PlaidTransactionProcessor implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private PlaidTransactionExtractor $extractor,
        private PlaidTransactionTransformer $transformer,
        private CreateBankFeedTransaction $loader,
    ) {
    }

    public function process(CashApplicationBankAccount $bankAccount, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $bankAccount->tenant()->useTimezone();

        try {
            $transactions = $this->extractor->extract($bankAccount->plaid_link, $start, $end);
        } catch (IntegrationApiException $e) {
            $this->logger->error('Could not extract Plaid transactions', ['exception' => $e]);

            return;
        }

        foreach ($transactions as $transaction) {
            if ($bankFeedTransaction = $this->transformer->transform($bankAccount, $transaction)) {
                try {
                    $this->loader->create($bankFeedTransaction);
                } catch (ModelException $e) {
                    $this->logger->error('Could not load Plaid transaction', ['exception' => $e]);
                    // keep processing other records in the event of an exception
                }
            }
        }

        $bankAccount->last_retrieved_data_at = time();
        $bankAccount->saveOrFail();
    }
}
