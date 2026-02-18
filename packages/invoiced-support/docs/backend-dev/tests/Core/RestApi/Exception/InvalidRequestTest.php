<?php

namespace App\Tests\Core\RestApi\Exception;

use App\Core\RestApi\Exception\InvalidRequest;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class InvalidRequestTest extends MockeryTestCase
{
    public function testGetMessage(): void
    {
        $error = new InvalidRequest('error');
        $this->assertEquals('error', $error->getMessage());
    }

    public function testGetStatusCode(): void
    {
        $error = new InvalidRequest('error', 401);
        $this->assertEquals(401, $error->getStatusCode());
    }

    public function testGetParam(): void
    {
        $error = new InvalidRequest('error', 401, 'username');
        $this->assertEquals('username', $error->getParam());
    }
}
