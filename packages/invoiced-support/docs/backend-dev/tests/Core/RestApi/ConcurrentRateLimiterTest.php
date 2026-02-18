<?php

namespace App\Tests\Core\RestApi;

use App\Core\RestApi\Libs\ConcurrentRateLimiter;
use App\Tests\AppTestCase;

class ConcurrentRateLimiterTest extends AppTestCase
{
    const CAPACITY = 100;

    const TTL = 60;

    public function testCheck(): void
    {
        $rateLimiter = new ConcurrentRateLimiter(self::CAPACITY, self::getService('test.redis'));
        $id = uniqid();

        // Pounding the server is fine as long as you finish the request
        for ($i = 0; $i < self::CAPACITY * 10; ++$i) {
            $this->assertTrue($rateLimiter->isAllowed($id), 'Serial requests should work');
            $rateLimiter->cleanUpAfterRequest($id);
        }

        // But concurrent is not
        for ($i = 0; $i < self::CAPACITY; ++$i) {
            $this->assertTrue($rateLimiter->isAllowed($id), 'Concurrent requests under limit should work');
        }

        $this->assertFalse($rateLimiter->isAllowed($id), 'Concurrent requests beyond capacity are not allowed');
    }

    public function testCheckNoLimit(): void
    {
        $rateLimiter = new ConcurrentRateLimiter(0, self::getService('test.redis'));
        $id = uniqid();

        for ($i = 0; $i < self::CAPACITY + 1; ++$i) {
            $this->assertTrue($rateLimiter->isAllowed($id), 'Concurrent requests when no limit should work');
        }
    }
}
