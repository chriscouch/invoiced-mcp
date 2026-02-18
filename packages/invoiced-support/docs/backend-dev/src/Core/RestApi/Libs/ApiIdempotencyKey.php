<?php

namespace App\Core\RestApi\Libs;

use App\Core\RestApi\Exception\ApiHttpException;
use App\Core\RestApi\Models\ApiKey;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiIdempotencyKey
{
    const KEY_HEADER = 'IDEMPOTENCY_KEY';

    const KEY_MIN_LENGTH = 6;

    const KEY_MAX_LENGTH = 64;

    const CACHED_RESPONSE_TTL = 86400;
    private CacheItemInterface $cacheItem;

    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    /**
     * @throws ApiHttpException
     */
    public static function getKeyFromRequest(Request $request): ?string
    {
        $key = $request->headers->get(self::KEY_HEADER);
        if (!$key) {
            return null;
        }

        self::validate($request, $key);

        return $key;
    }

    /**
     * Validates the user-supplied idempotency key.
     *
     * @throws ApiHttpException
     */
    private static function validate(Request $request, string $key): void
    {
        // Checks if an API request supports idempotency
        if (!in_array($request->getMethod(), ['POST', 'PATCH'])) {
            throw new ApiHttpException(400, 'Unsupported method: '.$request->getMethod().'. Idempotency keys can only be used with POST and PATCH requests.');
        }

        if (strlen($key) < self::KEY_MIN_LENGTH) {
            throw new ApiHttpException(400, 'Idempotency key is too short. Must be at least '.self::KEY_MIN_LENGTH.' characters long.');
        }

        if (strlen($key) > self::KEY_MAX_LENGTH) {
            throw new ApiHttpException(400, 'Idempotency key is too long. Must be no longer than '.self::KEY_MAX_LENGTH.' characters.');
        }
    }

    public function setKey(ApiKey $apiKey, string $key): void
    {
        $cacheKey = 'idempotency.'.$apiKey->id().$key;
        $this->cacheItem = $this->cache->getItem($cacheKey);
    }

    /**
     * Retrieves the cached API response.
     * NOTE: This assumes the response content type is always JSON.
     */
    public function getCachedResponse(): ?Response
    {
        $value = $this->cacheItem->get();
        if (!$value) {
            return null;
        }

        $response = json_decode($value);

        return new Response($response->body, $response->code);
    }

    /**
     * Caches an API response.
     */
    public function cacheResponse(Response $response): void
    {
        $response = json_encode([
            'body' => $response->getContent(),
            'code' => $response->getStatusCode(),
        ]);

        $this->cacheItem->set($response);
        $this->cacheItem->expiresAfter(self::CACHED_RESPONSE_TTL);
        $this->cache->save($this->cacheItem);
    }
}
