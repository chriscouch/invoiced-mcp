<?php

namespace App\Core\RestApi\Libs;

use App\Core\RestApi\Interfaces\RateLimiterInterface;
use App\Core\Utils\InfuseUtility as Utility;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Redis;

/**
 * Applies concurrent rate limiting to requests.
 */
class ConcurrentRateLimiter implements LoggerAwareInterface, RateLimiterInterface
{
    use LoggerAwareTrait;

    const REQUEST_TTL = 60;
    const LUA_LIMITER = 'local key = KEYS[1]

local capacity = tonumber(ARGV[1])
local timestamp = tonumber(ARGV[2])
local id = ARGV[3]

local count = redis.call("zcard", key)
local allowed = count < capacity

if allowed then
  redis.call("zadd", key, timestamp, id)
end

return { allowed, count }';
    private ?string $idInRedis = null;

    /**
     * @param int $capacity maximum # of concurrent requests allowed
     */
    public function __construct(
        private int $capacity,
        /** @var Redis $redisClient */
        private $redisClient,
        private string $cacheNamespace = ''
    ) {
    }

    public function isAllowed(string $userId): bool
    {
        if ($this->capacity <= 0) {
            return true;
        }

        // A string of some random characters.
        // Make it long enough to make sure two machines don't
        // have the same string in the same TTL.
        $id = Utility::guid(false);
        $key = $this->getCacheKey($userId);
        $timestamp = time();

        try {
            // Clear out old requests that probably got lost
            $this->redisClient->zRemRangeByScore($key, '-inf', (string) ($timestamp - self::REQUEST_TTL));
            $keys = [$key];
            $args = [$this->capacity, $timestamp, $id];
            [$allowed] = $this->redisClient->eval(self::LUA_LIMITER, array_merge($keys, $args), count($keys));
        } catch (\RedisException $e) {
            $this->logger->error('API rate limiter call to redis failed', ['exception' => $e]);

            // Fail open so Redis outages don't take down the API
            return true;
        }

        // Save it for later so we can remove it when the request is done
        if ($allowed) {
            $this->idInRedis = $id;
        }

        return (bool) $allowed;
    }

    public function cleanUpAfterRequest(string $userId): void
    {
        if (!$this->idInRedis) {
            return;
        }

        $key = $this->getCacheKey($userId);

        try {
            $this->redisClient->zRem($key, $this->idInRedis);
        } catch (\RedisException) {
            // no need to log here because it would have been caught in isAllowed()
        }
    }

    public function getErrorMessage(): string
    {
        return 'Too many concurrent requests! You can only make '.$this->capacity.' concurrent requests at a time.';
    }

    /**
     * Gets the key in redis for a request.
     */
    private function getCacheKey(string $userId): string
    {
        return $this->cacheNamespace.':concurrent_requests_limiter.'.$userId;
    }
}
