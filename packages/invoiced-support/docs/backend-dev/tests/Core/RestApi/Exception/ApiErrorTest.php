<?php

namespace App\Tests\Core\RestApi\Exception;

use App\Core\RestApi\Exception\ApiError;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class ApiErrorTest extends MockeryTestCase
{
    public function testGetMessage(): void
    {
        $error = new ApiError('error');
        $this->assertEquals('error', $error->getMessage());
    }

    public function testGetStatusCode(): void
    {
        $error = new ApiError('error', 500);
        $this->assertEquals(500, $error->getStatusCode());
    }
}
