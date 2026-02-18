<?php

namespace App\Integrations\Plaid\Libs;

use App\Companies\Models\Company;
use App\Core\Utils\DebugContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use stdClass;

class PlaidApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use IntegrationLogAwareTrait;

    private const URL = 'https://production.plaid.com';
    private const SANDBOX_URL = 'https://sandbox.plaid.com';

    private Client $client;

    public function __construct(
        private string $clientId,
        private string $secret,
        private bool $sandbox,
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext
    ) {
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    private function getClient(Company $company): Client
    {
        if (!isset($this->client)) {
            return new Client([
                'base_uri' => $this->sandbox ? self::SANDBOX_URL : self::URL,
                'headers' => ['User-Agent' => 'Invoiced/1.0'],
                'handler' => $this->makeGuzzleLogger('plaid', $company, $this->cloudWatchLogsClient, $this->debugContext),
            ]);
        }

        return $this->client;
    }

    /**
     * Creates a new Plaid link token to be passed to the Plaid Link widget.
     *
     * @throws IntegrationApiException
     */
    public function createLinkToken(Company $company, array $options): string
    {
        try {
            $response = $this->getClient($company)
                ->post('/link/token/create', [
                    'json' => array_merge($options, [
                        'client_id' => $this->clientId,
                        'secret' => $this->secret,
                        'client_name' => 'Invoiced',
                    ]),
                ]);

            return json_decode($response->getBody())->link_token;
        } catch (RequestException $e) {
            // Plaid does not support every customer country. If this is a country not supported
            // error, then we don't need to flag this kind of error.
            $body = (string) $e->getResponse()?->getBody();
            if (!str_contains($body, 'for a list of supported country codes')) {
                $this->logger->error('Unable to generate Plaid link token', ['exception' => $e]);
            }

            throw new IntegrationApiException('Unable to generate Plaid link token.', 0, $e);
        }
    }

    /**
     * Creates a new Plaid link token to be passed to the Plaid Link widget.
     *
     * @throws IntegrationApiException
     */
    public function createProcessorToken(Company $company, array $options): string
    {
        try {
            $response = $this->getClient($company)
                ->post('/processor/token/create', [
                    'json' => array_merge($options, [
                        'client_id' => $this->clientId,
                        'secret' => $this->secret,
                    ]),
                ]);

            return json_decode($response->getBody())->processor_token;
        } catch (RequestException $e) {
            // Plaid does not support every customer country. If this is a country not supported
            // error, then we don't need to flag this kind of error.
            $body = (string) $e->getResponse()?->getBody();
            if (!str_contains($body, 'for a list of supported country codes')) {
                $this->logger->error('Unable to generate Plaid link token', ['exception' => $e]);
            }

            throw new IntegrationApiException('Unable to generate Plaid link token.', 0, $e);
        }
    }

    /**
     * Exchanges a Plaid public token for an access token.
     *
     * @throws IntegrationApiException
     *
     * @return object The result object containing the access_token, item_id, and request_id
     */
    public function exchangePublicToken(Company $company, string $publicToken): object
    {
        try {
            $response = $this->getClient($company)
                ->post('/item/public_token/exchange', [
                    'json' => [
                        'client_id' => $this->clientId,
                        'secret' => $this->secret,
                        'public_token' => $publicToken,
                    ],
                ]);

            return json_decode($response->getBody());
        } catch (RequestException $e) {
            $this->logger->error('Unable to exchange Plaid public token', ['exception' => $e]);

            throw new IntegrationApiException('Unable to process Plaid token.', 0, $e);
        }
    }

    /**
     * Gets a list of transactions from a Plaid item within a given time frame.
     *
     * @throws IntegrationApiException
     */
    public function getTransactions(PlaidItem $plaidItem, CarbonImmutable $start, CarbonImmutable $end, int $perPage, int $offset): stdClass
    {
        try {
            $response = $this->getClient($plaidItem->tenant())
                ->post('/transactions/get', [
                    'json' => [
                        'client_id' => $this->clientId,
                        'secret' => $this->secret,
                        'access_token' => $plaidItem->access_token,
                        'start_date' => $start->format('Y-m-d'),
                        'end_date' => $end->format('Y-m-d'),
                        'options' => [
                            'count' => $perPage,
                            'offset' => $offset,
                        ],
                    ],
                ]);

            return json_decode($response->getBody());
        } catch (RequestException $e) {
            throw new IntegrationApiException($this->makeTransactionErrorMessage($e), $e->getCode(), $e);
        }
    }

    /**
     * Gets account info from a Plaid item.
     *
     * @throws IntegrationApiException
     */
    public function getAccount(PlaidItem $plaidItem): stdClass
    {
        try {
            $response = $this->getClient($plaidItem->tenant())
                ->post('/auth/get', [
                    'json' => [
                        'client_id' => $this->clientId,
                        'secret' => $this->secret,
                        'access_token' => $plaidItem->access_token,
                        'options' => [
                            'account_ids' => [$plaidItem->account_id],
                        ],
                    ],
                ]);

            $result = json_decode($response->getBody());
            $numbers = $result->numbers;
            if (1 === count($numbers)) {
                return $numbers[0];
            }

            throw new IntegrationApiException('Plaid returned '.count($numbers).' instead of one', 500);
        } catch (RequestException $e) {
            throw new IntegrationApiException($this->makeTransactionErrorMessage($e), $e->getCode(), $e);
        }
    }

    /**
     * Removes a Plaid item.
     *
     * @throws IntegrationApiException
     */
    public function removeItem(PlaidItem $plaidItem): void
    {
        try {
            $this->getClient($plaidItem->tenant())
                ->post('/item/remove', [
                    'json' => [
                        'client_id' => $this->clientId,
                        'secret' => $this->secret,
                        'access_token' => $plaidItem->access_token,
                    ],
                ]);
        } catch (RequestException $e) {
            throw new IntegrationApiException($this->makeTransactionErrorMessage($e), $e->getCode(), $e);
        }
    }

    private function makeTransactionErrorMessage(RequestException $e): string
    {
        $response = $e->getResponse();
        if (!$response) {
            return 'An unknown error has occurred when communicating with Plaid';
        }

        $result = json_decode($response->getBody());

        return $result->error_message;
    }
}
