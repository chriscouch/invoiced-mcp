<?php

namespace App\Companies\Verification;

use App\Companies\Exception\BusinessVerificationException;
use App\Core\Utils\LoggerFactory;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Redis;
use RedisException;
use stdClass;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

/**
 * The purpose of this class is to perform verification of a U.S. tax ID.
 * A tax ID can belong to a business (EIN) or individual (SSN). The tax verification
 * is using the IRS TIN Match service via the Compliancely API. The Compliancely
 * API is asynchronous which is why this class is using polling. There is also caching
 * and rate limiting in place to prevent extra submissions each of which costs money.
 */
class UsTaxIdVerification implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const MAX_RETRIES = 3;
    private const WAIT_BETWEEN_RETRIES = 15; // seconds
    private const MAX_POLL_TIME = 66; // seconds
    private const ATTEMPT_LOCKOUT_WINDOW = '1 hour';

    private const MAX_ATTEMPTS = 6;

    private int $totalRetries = 0;
    private int $pollWait = 5; // seconds
    private LoggerInterface $responseLogger;

    public function __construct(
        private string $compliancelyKey,
        private HttpClientInterface $httpClient,
        private LoggerFactory $loggerFactory,
        /** @var Redis $redisClient */
        protected $redisClient,
        private CacheInterface $cache,
        private Connection $database,
        private string $cacheNamespace = '',
    ) {
    }

    /**
     * Verifies a tax ID and name combination with the IRS.
     *
     * @throws BusinessVerificationException
     */
    public function verify(int $tenantId, string $name, string $taxId, bool $isEin): int
    {
        // Sanitize the tax ID to only numeric
        $taxId = (string) preg_replace('/[^0-9]+/i', '', $taxId);

        // Tax IDs are always 9 digits
        if (9 != strlen($taxId) || !preg_match('/^[0-9]*$/', $taxId)) {
            throw new BusinessVerificationException('Tax ID number should be 9-digit numeric');
        }

        // Check the tax ID against the block list
        $hash = md5('US_'.$taxId);
        $count = $this->database->fetchOne('SELECT COUNT(*) FROM BlockListTaxIds WHERE tax_id_hash=?', [$hash]);
        if ($count > 0) {
            throw new BusinessVerificationException('We were unable to validate the given name and tax ID with the IRS.');
        }

        // Sanitize the name to 40 characters and only IRS allowed characters.
        // Per the IRS, the only allowed characters in a business name are:
        // 1. Alpha
        // 2. Numeric
        // 3. Hyphen
        // 4. Ampersand
        $name = (string) preg_replace('/[^a-z0-9\-&\s]+/i', '', $name);
        $name = substr($name, 0, 40);

        // Obtain the IRS code and cache it for 1 day to prevent duplicate, costly submissions
        $cacheKey = $this->getCacheKey($name, $taxId);
        $irsCode = $this->cache->get($cacheKey, function (ItemInterface $cacheItem) use ($tenantId, $name, $taxId) {
            $irsCode = $this->getIrsCode($tenantId, $name, $taxId);
            $cacheItem->expiresAfter(86400); // one day

            return $irsCode;
        });

        // 0 - Matched IRS records
        if (0 == $irsCode) {
            return $irsCode;
        }

        // 6 - Matched a SSN
        if (6 == $irsCode && !$isEin) {
            return $irsCode;
        }

        // 7 - Matched an EIN
        if (7 == $irsCode && $isEin) {
            return $irsCode;
        }

        // 8 - Matched an EIN and SSN
        if (8 == $irsCode) {
            return $irsCode;
        }

        // Any other scenario is not a match
        throw new BusinessVerificationException('We were unable to validate the given name and tax ID with the IRS.');
    }

    /**
     * @throws BusinessVerificationException when the IRS code cannot be resolved (excluding -1 and 10 codes)
     */
    private function getIrsCode(int $tenantId, string $name, string $taxId): int
    {
        $result = $this->createSubmission($tenantId, $name, $taxId);
        $irsCode = $result->irs_code;

        // -1 - Pending check (Compliancely code)
        // 10 - In review (Compliancely code)
        if (-1 == $irsCode || 10 == $irsCode) {
            $irsCode = $this->pollSubmission((string) $result->id);
        }

        return $irsCode;
    }

    /**
     * @throws BusinessVerificationException when unable to create the submission
     */
    private function createSubmission(int $tenantId, string $name, string $taxId): stdClass
    {
        if ($this->totalRetries > self::MAX_RETRIES) {
            throw new BusinessVerificationException('The IRS tax ID verification service is currently down. Please try again later.');
        }

        // On the first request, check if this tenant has exceeded their maximum attempts within a certain time window
        if (0 == $this->totalRetries) {
            if ($this->getRemainingAttempts($tenantId) <= 0) {
                throw new BusinessVerificationException('Too many attempts. Please try again later.');
            }

            $this->recordAttempt($tenantId);
        }

        ++$this->totalRetries;

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://app1.compliancely.com/api/v1/submissions/',
                [
                    'headers' => [
                        'Authorization' => 'Token '.$this->compliancelyKey,
                    ],
                    'json' => [
                        'name' => $name,
                        'tin' => $taxId,
                    ],
                ],
            );

            /** @var stdClass $result */
            $result = json_decode($response->getContent());
            $this->logResponse($response);

            if (!isset($result->irs_code)) {
                sleep(self::WAIT_BETWEEN_RETRIES);

                return $this->createSubmission($tenantId, $name, $taxId);
            }

            return $result;
        } catch (ExceptionInterface $e) {
            if ($e instanceof HttpExceptionInterface) {
                // log the response
                $this->logResponse($e->getResponse());
            } else {
                // log the exception
                $this->logger->error('Unexpected failure when validating US tax ID', ['exception' => $e]);
            }

            sleep(self::WAIT_BETWEEN_RETRIES);

            return $this->createSubmission($tenantId, $name, $taxId);
        }
    }

    /**
     * @throws BusinessVerificationException when the submission is not able to resolve with an IRS code other than -1 or 10
     */
    private function pollSubmission(string $id): int
    {
        $start = time();
        while (time() - $start < self::MAX_POLL_TIME) {
            sleep($this->pollWait);

            try {
                $response = $this->httpClient->request(
                    'GET',
                    'https://app1.compliancely.com/api/v1/submissions/'.$id,
                    [
                        'headers' => [
                            'Authorization' => 'Token '.$this->compliancelyKey,
                        ],
                    ],
                );

                $result = json_decode($response->getContent());
                $this->logResponse($response);
                $irsCode = $result->irs_code;

                // Return as soon as we get a code that is not -1 or 10
                if (-1 != $irsCode && 10 != $irsCode) {
                    return $irsCode;
                }
            } catch (ExceptionInterface $e) {
                if ($e instanceof HttpExceptionInterface) {
                    // log the response
                    $this->logResponse($e->getResponse());
                }

                // do nothing on failure
            }
        }

        // If the result has not been returned then it is still pending or has failed
        throw new BusinessVerificationException('We were unable to validate the given name and tax ID with the IRS.');
    }

    private function logResponse(ResponseInterface $response): void
    {
        if (!isset($this->responseLogger)) {
            $this->responseLogger = $this->loggerFactory->get('fraud');
        }

        try {
            $result = $response->toArray(false);
            unset($result['tin']);
            $this->responseLogger->log('info', 'Response from Compliancely: '.json_encode($result));
        } catch (Throwable) {
            // ignore
        }
    }

    private function getRemainingAttempts(int $tenantId): int
    {
        $maxAttempts = self::MAX_ATTEMPTS;
        $key = $this->getCounterKey($tenantId);

        try {
            $failedAttempts = (int) $this->redisClient->get($key);

            return max(0, $maxAttempts - $failedAttempts);
        } catch (RedisException $e) {
            $this->logger->error('Tax ID verification rate limiter call to redis failed', ['exception' => $e]);

            return $maxAttempts;
        }
    }

    private function recordAttempt(int $tenantId): void
    {
        $key = $this->getCounterKey($tenantId);

        try {
            // increment a failed login counter in redis and update the expiration date
            $this->redisClient->incr($key);

            $window = self::ATTEMPT_LOCKOUT_WINDOW;
            $expiresIn = strtotime("+$window") - time();
            $this->redisClient->expire($key, $expiresIn);
        } catch (RedisException) {
            // no need to log here because it would have been caught in getRemainingAttempts()
        }
    }

    private function getCacheKey(string $name, string $taxId): string
    {
        return md5($name.'/'.$taxId);
    }

    private function getCounterKey(int $tenantId): string
    {
        return $this->cacheNamespace.':tax_id_verification_counter.'.$tenantId;
    }

    /**
     * Used for testing.
     */
    public function setPollWait(int $seconds): void
    {
        $this->pollWait = $seconds;
    }
}
