<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Libs\GatewayLogger;
use Aws\DynamoDb\DynamoDbClient;
use Mockery;
use PHPUnit\Framework\TestCase;

class GatewayLoggerTest extends TestCase
{
    public function testGetCurrentGateway(): void
    {
        $logger = new GatewayLogger(Mockery::mock(DynamoDbClient::class), Mockery::mock(TenantContext::class), 'test');
        $logger->setCurrentGateway('test');
        $this->assertEquals('test', $logger->getCurrentGateway());
        $logger->setCurrentGateway('test2');
        $this->assertEquals('test2', $logger->getCurrentGateway());
        Mockery::close();
    }

    public function testGetLastRequest(): void
    {
        $logger = new GatewayLogger(Mockery::mock(DynamoDbClient::class), Mockery::mock(TenantContext::class), 'test');
        $logger->addRequest('test');
        $this->assertEquals('test', $logger->getLastRequest());
        $logger->addRequest('test2');
        $this->assertEquals("test\n\ntest2", $logger->getLastRequest());
        $logger->setLastRequest(null);
        $logger->addRequest('test');
        $this->assertEquals('test', $logger->getLastRequest());
        Mockery::close();
    }

    public function testGetLastResponse(): void
    {
        $logger = new GatewayLogger(Mockery::mock(DynamoDbClient::class), Mockery::mock(TenantContext::class), 'test');
        $logger->setLastResponse('test');
        $this->assertEquals('test', $logger->getLastResponse());
        $logger->addResponse('test2');
        $this->assertEquals("test\n\ntest2", $logger->getLastResponse());
        $logger->setLastResponse(null);
        $logger->addResponse('test');
        $this->assertEquals('test', $logger->getLastResponse());
        Mockery::close();
    }
}
