<?php

namespace App\Tests\Core\RestApi;

use App\Core\RestApi\Exception\ApiHttpException;
use App\Core\RestApi\Libs\ApiIdempotencyKey;
use App\Core\RestApi\Models\ApiKey;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiIdempotencyKeyTest extends AppTestCase
{
    private function getIdempotencyKey(): ApiIdempotencyKey
    {
        return self::getService('test.api_idempotency_key');
    }

    private function getRequest(string $method = 'POST', ?string $key = null): Request
    {
        $key = $key ?? uniqid();

        return Request::create('/', $method, [], [], [], ['HTTP_IDEMPOTENCY_KEY' => $key]);
    }

    private function getApiKey(): ApiKey
    {
        return new ApiKey(['id' => 10]);
    }

    public function testGetKeyFromRequest(): void
    {
        $request = $this->getRequest('POST', 'testing');
        $this->assertEquals('testing', ApiIdempotencyKey::getKeyFromRequest($request));
    }

    public function testGetKeyFromRequestUnsupportedMethodGet(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('Unsupported method: GET. Idempotency keys can only be used with POST and PATCH requests.');

        $request = $this->getRequest('GET');
        ApiIdempotencyKey::getKeyFromRequest($request);
    }

    public function testGetKeyFromRequestUnsupportedMethodPut(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('Unsupported method: PUT. Idempotency keys can only be used with POST and PATCH requests.');

        $request = $this->getRequest('PUT');
        ApiIdempotencyKey::getKeyFromRequest($request);
    }

    public function testGetKeyFromRequestUnsupportedMethodDelete(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('Unsupported method: DELETE. Idempotency keys can only be used with POST and PATCH requests.');

        $request = $this->getRequest('DELETE');
        ApiIdempotencyKey::getKeyFromRequest($request);
    }

    public function testGetKeyFromRequestIdempotencyKeyTooShort(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('Idempotency key is too short. Must be at least 6 characters long.');

        $key = str_pad('', 5, '+');
        $request = $this->getRequest('POST', $key);
        ApiIdempotencyKey::getKeyFromRequest($request);
    }

    public function testGetKeyFromRequestIdempotencyKeyTooLong(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('Idempotency key is too long. Must be no longer than 64 characters.');

        $key = str_pad('', 65, '+');
        $request = $this->getRequest('POST', $key);
        ApiIdempotencyKey::getKeyFromRequest($request);
    }

    public function testCaching(): void
    {
        $response = new Response('Test', 201);

        $apiIdempotencyKey = $this->getIdempotencyKey();
        $key = uniqid();
        $apiIdempotencyKey->setKey($this->getApiKey(), $key);

        $this->assertNull($apiIdempotencyKey->getCachedResponse());
        $apiIdempotencyKey->cacheResponse($response);

        $apiIdempotencyKey = $this->getIdempotencyKey();
        $apiIdempotencyKey->setKey($this->getApiKey(), $key);
        $cachedResponse = $apiIdempotencyKey->getCachedResponse();
        $this->assertInstanceOf(Response::class, $cachedResponse);
        $this->assertEquals('Test', $response->getContent());
        $this->assertEquals(201, $response->getStatusCode());
    }
}
