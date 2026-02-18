<?php

namespace App\Integrations\Adyen\Operations;

use App\Companies\Models\Company;
use App\Core\Utils\RandomString;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\PaymentProcessing\Models\MerchantAccount;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class RunAdyenTopUpProcedure implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly GetAccountInformation $accountInformation,
        private readonly AdyenClient $adyen,
        private readonly bool $adyenLiveMode,
    ) {
    }


    public function perform(MerchantAccount $merchantAccount, Company $company, string $instrumentId = null, bool $dryRun = true): void
    {
        /** @var AdyenAccount $adyenAccount */
        $adyenAccount = AdyenAccount::one();

        $account = $this->accountInformation->balanceAccount($merchantAccount);
        if (!$account || !$adyenAccount->account_holder_id) {
            $this->logger->error("No account holder found");
            return;
        }

        $holder = $this->adyen->getAccountHolder($adyenAccount->account_holder_id);
        $accounts = array_map(fn($account) => $account['id'], $holder['capabilities']['receiveFromTransferInstrument']['transferInstruments']);

        foreach ($account['balances'] as $balance) {
            $amount = -1 * $balance['balance'];
            if ($amount < 0) {
                $this->logger->error("Ignored Positive balance of : " . $amount . " " . $balance['currency']);
                continue;
            }

            $this->logger->debug(" -> Running top up Adyen API for company: " . $company->name . " for the amount " .
                $amount . " " . $balance['currency']);

            $reference = RandomString::generate();
            $method = match($balance['currency']) {
                'EUR' => 'sepadirectdebit',
                'GBP' => 'directdebit_GB',
                default => 'ach',
            };

            $parameters = [
                'merchantAccount' => AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $company->country),
                'amount' => [
                    'currency' => $balance['currency'],
                    'value' => $amount,
                ],
                'reference' => $reference,
                'returnUrl' => 'https://invoiced.com',
                'paymentMethod' => [
                    'type' => $method,
                    'transferInstrumentId' => $instrumentId ?? $accounts[0],
                ],
                'splits' => [
                    [
                        'amount' => [
                            'value' => $amount,
                        ],
                        'type' => 'TopUp',
                        'account' => $account['id'],
                    ],
                ],
            ];

            $this->logger->debug("Payment: " . json_encode($parameters));

            if (!$dryRun) {
                $adyenResult = $this->adyen->createPayment($parameters);

                $this->logger->debug("Adyen result: " . json_encode($adyenResult));
            }
        }
    }
}