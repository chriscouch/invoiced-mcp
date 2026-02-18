<?php

namespace App\Integrations\QuickBooksDesktop;

use App\Companies\Models\Company;
use App\Core\Files\Interfaces\FileCreatorInterface;
use App\Core\RestApi\Models\ApiKey;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use App\Core\Utils\InfuseUtility as Utility;
use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Exceptions\SyncAuthException;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\ValueObjects\SyncJob;
use App\Integrations\Enums\IntegrationType;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class QuickBooksDesktopSyncManager implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    private const CONNECT_TIMEOUT = 10; // seconds
    private const READ_TIMEOUT = 30; // seconds


    public function __construct(
        private readonly FileCreatorInterface $s3FileCreator,
        private string $bucket,
        private DebugContext $debugContext,
        private string $syncserverHost,
        private string $syncserverUser,
        private string $syncserverPassword
    ) {
    }

    /**
     * Retrieves the most recent sync jobs for this company.
     *
     * @param Client $client used for testing
     *
     * @return SyncJob[]
     */
    public function getJobs(Company $company, Client $client = null): array
    {
        if (!$client) {
            $client = $this->buildHttpClient($company);
        }

        $base = $this->syncserverHost;
        $endpoint = "$base/syncs/{$company->id}";

        try {
            $response = $client->get($endpoint);
        } catch (TransferException $e) {
            $this->statsd->increment('accounting_sync.api_call_fail');
            $this->logger->error('Unable to retrieve sync jobs', ['exception' => $e]);

            return [];
        }

        $result = json_decode($response->getBody(), true);
        $jobs = $result['syncs'];

        foreach ($jobs as &$job) {
            $job = $this->buildJob($job);
        }

        return $jobs;
    }

    /**
     * Gets the cancel endpoint for a sync job.
     */
    public function getStopEndpoint(Company $company, string $id): string
    {
        $base = $this->syncserverHost;

        return "$base/syncs/{$company->id()}/$id";
    }

    /**
     * Gets the synced records endpoint for a sync job.
     */
    public function getSyncedRecordsEndpoint(Company $company, string $id): string
    {
        $base = $this->syncserverHost;

        return "$base/synced_records/{$company->id()}/$id";
    }

    /**
     * Gets the skipped records endpoint.
     */
    public function getSkippedRecordsEndpoint(): string
    {
        $base = $this->syncserverHost;

        return "$base/skipped_records";
    }

    /**
     * Gets the endpoint for connecting QB Desktop.
     */
    public function getConnectQuickBooksDesktopEndpoint(): string
    {
        $base = $this->syncserverHost;

        return "$base/integrations/qwc/file";
    }

    /**
     * Builds a job object from a syncserver response.
     */
    public function buildJob(array $params): SyncJob
    {
        // determine the integration associated with the job
        try {
            $integrationType = IntegrationType::QuickBooksDesktop;
            $params['integration'] = [
                'id' => $integrationType->toString(),
                'name' => $integrationType->toHumanName(),
            ];
        } catch (InvalidArgumentException) {
            $params['integration'] = [
                'id' => null,
                'name' => null,
            ];
        }

        return new SyncJob($params);
    }

    /**
     * Stops a sync job.
     *
     * @param Client $client used for testing
     *
     * @throws SyncException when the job cannot be stopped
     */
    public function stopJob(Company $company, string $id, Client $client = null): SyncJob
    {
        if (!$client) {
            $client = $this->buildHttpClient($company);
        }

        $endpoint = $this->getStopEndpoint($company, $id);

        try {
            $response = $client->delete($endpoint);
        } catch (TransferException $e) {
            throw $this->handleErrorResponse($e, 'An error occurred when stopping the sync');
        }

        $result = json_decode($response->getBody(), true);

        return $this->buildJob($result);
    }

    /**
     * Gets the synced records for a sync.
     *
     * @param Client $client used for testing
     *
     * @throws SyncException when the records cannot be retrieved
     */
    public function getSyncedRecords(Company $company, string $id, Client $client = null): array
    {
        if (!$client) {
            $client = $this->buildHttpClient($company);
        }

        $endpoint = $this->getSyncedRecordsEndpoint($company, $id);

        try {
            $response = $client->get($endpoint);
        } catch (TransferException $e) {
            throw $this->handleErrorResponse($e, 'An error occurred when retrieving the synced records');
        }

        return (array) json_decode($response->getBody());
    }

    /**
     * Marks a record to be skipped by the sync.
     *
     * @param string $object object type, i.e. `invoice`
     *
     * @throws SyncException when the operation fails
     */
    public function skipRecord(Company $company, string $type, string $object, string $id, Client $client = null): void
    {
        if (!$client) {
            $client = $this->buildHttpClient($company);
        }

        $endpoint = $this->getSkippedRecordsEndpoint();

        $params = [
            'sync_type' => $type,
            'tenant_id' => (int) $company->id(),
            'object' => $object,
            'id' => $id,
        ];

        try {
            $client->post($endpoint, [
                'json' => $params,
            ]);
        } catch (TransferException $e) {
            throw $this->handleErrorResponse($e, 'An error occurred when skipping the record');
        }

        // the response should be an empty body
        // if we made it here then it worked
    }

    /**
     * Enables a QuickBooks Desktop integration.
     *
     * @param Client|null $client used for testing
     *
     * @throws SyncException when the integration cannot be created
     */
    public function enableQuickBooksEnterprise(Company $company, Client $client = null): array
    {
        if (!$client) {
            $client = $this->buildHttpClient($company);
        }

        $invoicedApiKey = $company->getProtectedApiKey(ApiKey::SOURCE_SYNC);

        $params = [
            'sync_params' => [
                'companyID' => (int) $company->id(),
                'invdAPIKey' => $invoicedApiKey->secret,
                // These parameters are added for BC and can eventually be
                // removed once the quickbooks-desktop project no longer requires them.
                'time_zone' => $company->time_zone,
                'debug' => $company->features->has('log_quickbooks_desktop'),
                'parent_child_enabled' => false,
                'all_invoices' => true,
            ],
        ];

        $accountingSyncProfile = AccountingSyncModelFactory::getSyncProfile(IntegrationType::QuickBooksDesktop, $company);
        if ($accountingSyncProfile) {
            $parameters = $accountingSyncProfile->parameters;
            $params['sync_params']['parent_child_enabled'] = $parameters->parent_child_enabled ?? false;
            $params['sync_params']['all_invoices'] = $parameters->all_invoices ?? true;
        }

        try {
            $response = $client->post($this->getConnectQuickBooksDesktopEndpoint(), [
                'json' => $params,
            ]);
        } catch (TransferException $e) {
            $this->statsd->increment('accounting_sync.enable_qbd_fail');
            $this->logger->error('Fail', ['exception' => $e]);
            throw $this->handleErrorResponse($e, 'An error occurred when setting up the QuickBooks Desktop integration');
        }

        $result = json_decode((string) $response->getBody());

        return [
            'username' => $result->username,
            'password' => $result->password,
            'file' => $this->persist($result->file->name, $result->file->payload),
        ];
    }

    /**
     * Builds an HTTP client.
     */
    private function buildHttpClient(Company $company): Client
    {
        $username = $this->syncserverUser;
        $password = $this->syncserverPassword;

        $retryDecider = function (
            $retries,
            Request $request,
            ?Response $response = null,
            ?Throwable $exception = null
        ) {
            // Limit the number of retries to 5
            if ($retries >= 5) {
                return false;
            }

            // Retry connection exceptions
            if ($exception instanceof ConnectException) {
                return true;
            }

            // Retry on server errors
            if ($exception instanceof ServerException) {
                return true;
            }

            return false;
        };

        $retryDelay = fn ($numberOfRetries) => 1000 * $numberOfRetries;

        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($retryDecider, $retryDelay));

        return new Client([
            'auth' => [$username, $password],
            'handler' => $handlerStack,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'read_timeout' => self::READ_TIMEOUT,
            'headers' => [
                'User-Agent' => 'Invoiced/1.0',
                'X-Tenant-Id' => $company->id(),
                'X-Correlation-Id' => $this->debugContext->getCorrelationId(),
            ],
        ]);
    }

    /**
     * Handles an error response from the syncserver.
     */
    private function handleErrorResponse(TransferException $e, string $baseMessage): SyncException
    {
        $message = $baseMessage;

        $this->statsd->increment('accounting_sync.api_call_fail');

        // attempt to get an error message from the response
        if ($e instanceof BadResponseException) {
            $response = $e->getResponse();
            $error = json_decode($response->getBody());
            if (is_object($error)) {
                $message .= ': '.$error->message;
            } else {
                $this->logger->error($baseMessage, ['exception' => $e]);
                $message .= '.';
            }

            if (401 == $response->getStatusCode()) {
                throw new SyncAuthException($message);
            }
        } else {
            $message .= '.';

            $this->logger->error($baseMessage, ['exception' => $e]);
        }

        return new SyncException($message);
    }

    /**
     * Persists data to S3 using a randomized filename.
     *
     * @throws SyncException when the file cannot be persisted
     *
     * @return string resulting url
     */
    public function persist(string $filename, string $contents): string
    {
        $key = strtolower(Utility::guid());

        try {
            $file = $this->s3FileCreator->create($this->bucket, $filename, $contents, $key, [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $contents,
                'ContentDisposition' => 'attachment; filename="'.$filename.'"',
                'ContentType' => 'application/octet-stream',
            ]);
        } catch (S3Exception $e) {
            $this->logger->error('Could not upload QWC file', ['exception' => $e]);

            throw new SyncException('Could not save generated QWC file', 0, $e);
        }


        return $file->url;
    }
}
