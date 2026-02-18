<?php

namespace App\Integrations\Avalara;

use App\Companies\Models\Company;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Traits\IntegrationLogAwareTrait;
use Avalara\AvaTaxClient;
use Avalara\CappedFetchResult;
use Aws\CloudWatchLogs\CloudWatchLogsClient;

class AvalaraApi implements StatsdAwareInterface
{
    use IntegrationLogAwareTrait;
    use StatsdAwareTrait;

    private const TIMEOUT = 10;
    private const TIMEOUT_SANDBOX = 30;
    private AvaTaxClient $client;

    public function __construct(
        private CloudWatchLogsClient $cloudWatchLogsClient,
        private DebugContext $debugContext,
        private string $environment
    ) {
    }

    public function setClient(AvaTaxClient $client): void
    {
        $this->client = $client;
    }

    /**
     * Gets the Avalara client for an account.
     */
    public function getClientForAccount(AvalaraAccount $account): AvaTaxClient
    {
        // This is not 1:1 with API calls but is close enough
        $this->statsd->increment('avalara.api_call');

        return $this->getClient($account->account_id, $account->license_key, $account->tenant());
    }

    /**
     * Builds an instance of the Avalara client.
     */
    public function getClient(string $accountId, string $licenseKey, Company $company): AvaTaxClient
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $guzzleParams = [
            'connect_timeout' => 'sandbox' == $this->environment ? self::TIMEOUT_SANDBOX : self::TIMEOUT,
            'read_timeout' => 'sandbox' == $this->environment ? self::TIMEOUT_SANDBOX : self::TIMEOUT,
            'handler' => $this->makeGuzzleLogger('avalara', $company, $this->cloudWatchLogsClient, $this->debugContext),
            'headers' => [
                'User-Agent' => 'Invoiced/1.0',
            ],
        ];

        // INVD-3047: The Avalara SDK will set a timeout of 20 minutes if
        // the `timeout` parameter is not supplied.
        $guzzleParams['timeout'] = $guzzleParams['connect_timeout'] + $guzzleParams['read_timeout'];

        // This is not 1:1 with API calls but is close enough
        $this->statsd->increment('avalara.api_call');

        $client = new AvaTaxClient('Invoiced', '1.0', '', $this->environment, $guzzleParams);
        $client->withLicenseKey((int) $accountId, $licenseKey)
            ->withCatchExceptions(false);

        return $client;
    }

    /**
     * Gets a list of companies associated with the Avalara account. This
     * is useful for the connection phase to validate credentials and also
     * present the user with a list of companies to choose from.
     *
     * @throws IntegrationApiException
     */
    public function getCompanies(string $accountId, string $licenseKey, Company $company): array
    {
        $client = $this->getClient($accountId, $licenseKey, $company);

        try {
            /** @var CappedFetchResult $result */
            $result = $client->queryCompanies();
        } catch (\Exception $e) {
            throw new IntegrationApiException('We were unable to connect to Avalara with the given credentials. Please verify that your account ID and license key are correct.', $e->getCode(), $e);
        }

        $companies = [];
        foreach ($result->value as $company) {
            $companies[] = [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->companyCode,
            ];
        }

        return $companies;
    }
}
