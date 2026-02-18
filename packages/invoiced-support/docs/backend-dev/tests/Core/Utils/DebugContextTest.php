<?php

namespace App\Tests\Core\Utils;

use App\Core\Utils\DebugContext;
use App\Tests\AppTestCase;

class DebugContextTest extends AppTestCase
{
    public function testEnvironment(): void
    {
        $this->assertEquals('test', $this->getContext()->getEnvironment());
    }

    public function testGetRequestId(): void
    {
        $context = $this->getContext();
        $this->assertNull($context->getRequestId());

        $context->generateRequestId();
        $this->assertNotEquals('', $context->getRequestId());
        $this->assertEquals($context->getRequestId(), $context->getCorrelationId());

        $context->setRequestId('1234');
        $this->assertEquals('1234', $context->getRequestId());
        $this->assertEquals('1234', $context->getCorrelationId());
    }

    public function testGetCorrelationId(): void
    {
        $context = $this->getContext();
        $this->assertNotEquals('', $context->getCorrelationId());

        $context->setCorrelationId('456');
        $this->assertEquals('456', $context->getCorrelationId());
        $this->assertNotEquals($context->getRequestId(), $context->getCorrelationId());
    }

    private function getContext(): DebugContext
    {
        return new DebugContext('test');
    }
}
